<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_budget_revisions', function (Blueprint $table): void {
            $table->foreignId('previous_investment_source_id')->nullable()->index()->constrained('investment_sources')->restrictOnDelete();
            $table->text('previous_source_reference')->nullable();
            $table->string('previous_funding_source')->nullable();
            $table->string('new_funding_source')->nullable();
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION simred_guard_project_source() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE source_institution bigint;
BEGIN
    IF NEW.primary_investment_source_id IS NOT NULL THEN
        SELECT institution_id INTO source_institution FROM investment_sources WHERE id = NEW.primary_investment_source_id FOR SHARE;
        IF source_institution IS NOT NULL AND source_institution <> NEW.institution_id THEN
            RAISE EXCEPTION 'La fuente principal no corresponde a la institución del proyecto.' USING ERRCODE = '23514';
        END IF;
    END IF;
    IF TG_OP = 'UPDATE' AND NEW.institution_id IS DISTINCT FROM OLD.institution_id THEN
        IF EXISTS (SELECT 1 FROM project_budgets b JOIN investment_sources s ON s.id = b.investment_source_id WHERE b.project_id = NEW.id AND s.institution_id <> NEW.institution_id)
            OR EXISTS (SELECT 1 FROM project_budget_revisions r JOIN project_budgets b ON b.id = r.project_budget_id JOIN investment_sources s ON s.id IN (r.investment_source_id, r.previous_investment_source_id) WHERE b.project_id = NEW.id AND s.institution_id <> NEW.institution_id) THEN
            RAISE EXCEPTION 'El cambio de institución invalidaría las fuentes presupuestarias.' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION simred_guard_source_owner() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.institution_id IS DISTINCT FROM OLD.institution_id AND (
        EXISTS (SELECT 1 FROM projects WHERE primary_investment_source_id = NEW.id AND institution_id <> NEW.institution_id)
        OR EXISTS (SELECT 1 FROM project_budgets b JOIN projects p ON p.id = b.project_id WHERE b.investment_source_id = NEW.id AND p.institution_id <> NEW.institution_id)
        OR EXISTS (SELECT 1 FROM project_budget_revisions r JOIN project_budgets b ON b.id = r.project_budget_id JOIN projects p ON p.id = b.project_id WHERE (r.investment_source_id = NEW.id OR r.previous_investment_source_id = NEW.id) AND p.institution_id <> NEW.institution_id)
    ) THEN
        RAISE EXCEPTION 'La institución de una fuente vinculada debe conservar coherencia con sus proyectos.' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION simred_guard_budget_update() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE project_institution bigint; source_institution bigint; audit project_budget_revisions%ROWTYPE;
BEGIN
    SELECT institution_id INTO project_institution FROM projects WHERE id = NEW.project_id FOR SHARE;
    IF NEW.investment_source_id IS NOT NULL THEN
        SELECT institution_id INTO source_institution FROM investment_sources WHERE id = NEW.investment_source_id FOR SHARE;
        IF source_institution IS NOT NULL AND source_institution <> project_institution THEN
            RAISE EXCEPTION 'La fuente presupuestaria no corresponde a la institución del proyecto.' USING ERRCODE = '23514';
        END IF;
    END IF;
    IF TG_OP = 'UPDATE' THEN
        IF NEW.project_id <> OLD.project_id OR NEW.fiscal_year <> OLD.fiscal_year THEN
            RAISE EXCEPTION 'No se puede reasignar la identidad de un presupuesto anual.' USING ERRCODE = '23514';
        END IF;
        IF ROW(NEW.amount_bob, NEW.availability_status, NEW.investment_source_id, NEW.source_reference, NEW.funding_source)
            IS DISTINCT FROM ROW(OLD.amount_bob, OLD.availability_status, OLD.investment_source_id, OLD.source_reference, OLD.funding_source) THEN
            SELECT * INTO audit FROM project_budget_revisions WHERE project_budget_id = OLD.id ORDER BY revision_number DESC LIMIT 1;
            IF NOT FOUND OR ROW(audit.previous_amount_bob, audit.previous_availability_status, audit.previous_investment_source_id, audit.previous_source_reference, audit.previous_funding_source, audit.new_amount_bob, audit.new_availability_status, audit.investment_source_id, audit.source_reference, audit.new_funding_source)
                IS DISTINCT FROM ROW(OLD.amount_bob, OLD.availability_status, OLD.investment_source_id, OLD.source_reference, OLD.funding_source, NEW.amount_bob, NEW.availability_status, NEW.investment_source_id, NEW.source_reference, NEW.funding_source) THEN
                RAISE EXCEPTION 'Registre una revisión antes de modificar el presupuesto vigente.' USING ERRCODE = '23514';
            END IF;
        END IF;
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION simred_guard_budget_revision() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE budget project_budgets%ROWTYPE; previous_revision project_budget_revisions%ROWTYPE; next_number integer; project_institution bigint; source_institution bigint;
BEGIN
    IF TG_OP <> 'INSERT' THEN
        RAISE EXCEPTION 'Las revisiones presupuestarias son históricas e inmutables.' USING ERRCODE = '23514';
    END IF;
    SELECT * INTO budget FROM project_budgets WHERE id = NEW.project_budget_id FOR UPDATE;
    IF NOT FOUND THEN
        RETURN NEW;
    END IF;
    SELECT * INTO previous_revision FROM project_budget_revisions WHERE project_budget_id = budget.id ORDER BY revision_number DESC LIMIT 1;
    IF FOUND AND (
        ROW(previous_revision.new_amount_bob, previous_revision.new_availability_status, previous_revision.investment_source_id, previous_revision.source_reference)
            IS DISTINCT FROM ROW(budget.amount_bob, budget.availability_status, budget.investment_source_id, budget.source_reference)
        -- Legacy revisions have unknown funding in both nullable columns.
        OR ((previous_revision.previous_funding_source IS NOT NULL OR previous_revision.new_funding_source IS NOT NULL)
            AND previous_revision.new_funding_source IS DISTINCT FROM budget.funding_source)
    ) THEN
        RAISE EXCEPTION 'Aplique la revisión pendiente antes de registrar la siguiente.' USING ERRCODE = '23514';
    END IF;
    next_number := COALESCE(previous_revision.revision_number, 0) + 1;
    IF NEW.revision_number <> next_number OR ROW(NEW.previous_amount_bob, NEW.previous_availability_status, NEW.previous_investment_source_id, NEW.previous_source_reference, NEW.previous_funding_source) IS DISTINCT FROM ROW(budget.amount_bob, budget.availability_status, budget.investment_source_id, budget.source_reference, budget.funding_source) THEN
        RAISE EXCEPTION 'La revisión debe continuar el historial y conservar el valor vigente anterior.' USING ERRCODE = '23514';
    END IF;
    IF ROW(NEW.new_amount_bob, NEW.new_availability_status, NEW.investment_source_id, NEW.source_reference, NEW.new_funding_source)
        IS NOT DISTINCT FROM ROW(budget.amount_bob, budget.availability_status, budget.investment_source_id, budget.source_reference, budget.funding_source) THEN
        RAISE EXCEPTION 'Una revisión requiere un cambio real.' USING ERRCODE = '23514';
    END IF;
    SELECT institution_id INTO project_institution FROM projects WHERE id = budget.project_id FOR SHARE;
    IF NEW.investment_source_id IS NOT NULL THEN
        SELECT institution_id INTO source_institution FROM investment_sources WHERE id = NEW.investment_source_id FOR SHARE;
        IF source_institution IS NOT NULL AND source_institution <> project_institution THEN
            RAISE EXCEPTION 'La fuente de la revisión no corresponde a la institución del proyecto.' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION simred_check_revision_applied() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE budget project_budgets%ROWTYPE; audit project_budget_revisions%ROWTYPE;
BEGIN
    SELECT * INTO budget FROM project_budgets WHERE id = NEW.project_budget_id;
    SELECT * INTO audit FROM project_budget_revisions WHERE project_budget_id = NEW.project_budget_id ORDER BY revision_number DESC LIMIT 1;
    IF ROW(budget.amount_bob, budget.availability_status, budget.investment_source_id, budget.source_reference, budget.funding_source)
        IS DISTINCT FROM ROW(audit.new_amount_bob, audit.new_availability_status, audit.investment_source_id, audit.source_reference, audit.new_funding_source) THEN
        RAISE EXCEPTION 'La revisión y el valor vigente deben confirmarse en la misma transacción.' USING ERRCODE = '23514';
    END IF;
    RETURN NULL;
END;
$$;
SQL);
    }

    public function down(): void
    {
        DB::statement('LOCK TABLE project_budget_revisions IN ACCESS EXCLUSIVE MODE');

        if (DB::table('project_budget_revisions')
            ->whereNotNull('previous_investment_source_id')
            ->orWhereNotNull('previous_source_reference')
            ->orWhereNotNull('previous_funding_source')
            ->orWhereNotNull('new_funding_source')
            ->exists()) {
            throw new RuntimeException('No se puede revertir: las nuevas columnas contienen trazabilidad histórica que se perdería.');
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION simred_guard_project_source() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE source_institution bigint;
BEGIN
    IF NEW.primary_investment_source_id IS NOT NULL THEN
        SELECT institution_id INTO source_institution FROM investment_sources WHERE id = NEW.primary_investment_source_id FOR SHARE;
        IF source_institution IS NOT NULL AND source_institution <> NEW.institution_id THEN
            RAISE EXCEPTION 'La fuente principal no corresponde a la institución del proyecto.' USING ERRCODE = '23514';
        END IF;
    END IF;
    IF TG_OP = 'UPDATE' AND NEW.institution_id IS DISTINCT FROM OLD.institution_id THEN
        IF EXISTS (SELECT 1 FROM project_budgets b JOIN investment_sources s ON s.id = b.investment_source_id WHERE b.project_id = NEW.id AND s.institution_id <> NEW.institution_id)
            OR EXISTS (SELECT 1 FROM project_budget_revisions r JOIN project_budgets b ON b.id = r.project_budget_id JOIN investment_sources s ON s.id = r.investment_source_id WHERE b.project_id = NEW.id AND s.institution_id <> NEW.institution_id) THEN
            RAISE EXCEPTION 'El cambio de institución invalidaría las fuentes presupuestarias.' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION simred_guard_source_owner() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.institution_id IS DISTINCT FROM OLD.institution_id AND (
        EXISTS (SELECT 1 FROM projects WHERE primary_investment_source_id = NEW.id AND institution_id <> NEW.institution_id)
        OR EXISTS (SELECT 1 FROM project_budgets b JOIN projects p ON p.id = b.project_id WHERE b.investment_source_id = NEW.id AND p.institution_id <> NEW.institution_id)
        OR EXISTS (SELECT 1 FROM project_budget_revisions r JOIN project_budgets b ON b.id = r.project_budget_id JOIN projects p ON p.id = b.project_id WHERE r.investment_source_id = NEW.id AND p.institution_id <> NEW.institution_id)
    ) THEN
        RAISE EXCEPTION 'La institución de una fuente vinculada debe conservar coherencia con sus proyectos.' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION simred_guard_budget_update() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE project_institution bigint; source_institution bigint; audit project_budget_revisions%ROWTYPE;
BEGIN
    SELECT institution_id INTO project_institution FROM projects WHERE id = NEW.project_id FOR SHARE;
    IF NEW.investment_source_id IS NOT NULL THEN
        SELECT institution_id INTO source_institution FROM investment_sources WHERE id = NEW.investment_source_id FOR SHARE;
        IF source_institution IS NOT NULL AND source_institution <> project_institution THEN
            RAISE EXCEPTION 'La fuente presupuestaria no corresponde a la institución del proyecto.' USING ERRCODE = '23514';
        END IF;
    END IF;
    IF TG_OP = 'UPDATE' THEN
        IF NEW.project_id <> OLD.project_id OR NEW.fiscal_year <> OLD.fiscal_year THEN
            RAISE EXCEPTION 'No se puede reasignar la identidad de un presupuesto anual.' USING ERRCODE = '23514';
        END IF;
        IF ROW(NEW.amount_bob, NEW.availability_status, NEW.investment_source_id, NEW.source_reference)
            IS DISTINCT FROM ROW(OLD.amount_bob, OLD.availability_status, OLD.investment_source_id, OLD.source_reference) THEN
            SELECT * INTO audit FROM project_budget_revisions WHERE project_budget_id = OLD.id ORDER BY revision_number DESC LIMIT 1;
            IF NOT FOUND OR ROW(audit.previous_amount_bob, audit.previous_availability_status, audit.new_amount_bob, audit.new_availability_status, audit.investment_source_id, audit.source_reference)
                IS DISTINCT FROM ROW(OLD.amount_bob, OLD.availability_status, NEW.amount_bob, NEW.availability_status, NEW.investment_source_id, NEW.source_reference) THEN
                RAISE EXCEPTION 'Registre una revisión antes de modificar el presupuesto vigente.' USING ERRCODE = '23514';
            END IF;
        END IF;
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION simred_guard_budget_revision() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE budget project_budgets%ROWTYPE; next_number integer; project_institution bigint; source_institution bigint;
BEGIN
    IF TG_OP <> 'INSERT' THEN
        RAISE EXCEPTION 'Las revisiones presupuestarias son históricas e inmutables.' USING ERRCODE = '23514';
    END IF;
    SELECT * INTO budget FROM project_budgets WHERE id = NEW.project_budget_id FOR UPDATE;
    IF NOT FOUND THEN
        RETURN NEW;
    END IF;
    SELECT COALESCE(MAX(revision_number), 0) + 1 INTO next_number FROM project_budget_revisions WHERE project_budget_id = budget.id;
    IF NEW.revision_number <> next_number OR ROW(NEW.previous_amount_bob, NEW.previous_availability_status) IS DISTINCT FROM ROW(budget.amount_bob, budget.availability_status) THEN
        RAISE EXCEPTION 'La revisión debe continuar el historial y conservar el valor vigente anterior.' USING ERRCODE = '23514';
    END IF;
    IF ROW(NEW.new_amount_bob, NEW.new_availability_status, NEW.investment_source_id, NEW.source_reference)
        IS NOT DISTINCT FROM ROW(budget.amount_bob, budget.availability_status, budget.investment_source_id, budget.source_reference) THEN
        RAISE EXCEPTION 'Una revisión requiere un cambio real.' USING ERRCODE = '23514';
    END IF;
    SELECT institution_id INTO project_institution FROM projects WHERE id = budget.project_id FOR SHARE;
    IF NEW.investment_source_id IS NOT NULL THEN
        SELECT institution_id INTO source_institution FROM investment_sources WHERE id = NEW.investment_source_id FOR SHARE;
        IF source_institution IS NOT NULL AND source_institution <> project_institution THEN
            RAISE EXCEPTION 'La fuente de la revisión no corresponde a la institución del proyecto.' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION simred_check_revision_applied() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE budget project_budgets%ROWTYPE; audit project_budget_revisions%ROWTYPE;
BEGIN
    SELECT * INTO budget FROM project_budgets WHERE id = NEW.project_budget_id;
    SELECT * INTO audit FROM project_budget_revisions WHERE project_budget_id = NEW.project_budget_id ORDER BY revision_number DESC LIMIT 1;
    IF ROW(budget.amount_bob, budget.availability_status, budget.investment_source_id, budget.source_reference)
        IS DISTINCT FROM ROW(audit.new_amount_bob, audit.new_availability_status, audit.investment_source_id, audit.source_reference) THEN
        RAISE EXCEPTION 'La revisión y el valor vigente deben confirmarse en la misma transacción.' USING ERRCODE = '23514';
    END IF;
    RETURN NULL;
END;
$$;
SQL);

        Schema::table('project_budget_revisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('previous_investment_source_id');
            $table->dropColumn(['previous_source_reference', 'previous_funding_source', 'new_funding_source']);
        });
    }
};
