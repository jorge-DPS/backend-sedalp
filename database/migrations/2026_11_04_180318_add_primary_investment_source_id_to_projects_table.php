<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('primary_investment_source_id')->nullable()->index()->constrained('investment_sources')->restrictOnDelete();
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
            OR EXISTS (SELECT 1 FROM project_budget_revisions r JOIN project_budgets b ON b.id = r.project_budget_id JOIN investment_sources s ON s.id = r.investment_source_id WHERE b.project_id = NEW.id AND s.institution_id <> NEW.institution_id) THEN
            RAISE EXCEPTION 'El cambio de institución invalidaría las fuentes presupuestarias.' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER projects_source_institution_guard BEFORE INSERT OR UPDATE ON projects FOR EACH ROW EXECUTE FUNCTION simred_guard_project_source();

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
CREATE TRIGGER investment_sources_owner_guard BEFORE UPDATE ON investment_sources FOR EACH ROW EXECUTE FUNCTION simred_guard_source_owner();

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
CREATE TRIGGER project_budgets_update_guard BEFORE INSERT OR UPDATE ON project_budgets FOR EACH ROW EXECUTE FUNCTION simred_guard_budget_update();

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
CREATE TRIGGER project_budget_revisions_history_guard BEFORE INSERT OR UPDATE OR DELETE ON project_budget_revisions FOR EACH ROW EXECUTE FUNCTION simred_guard_budget_revision();

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
CREATE CONSTRAINT TRIGGER project_budget_revision_applied_check AFTER INSERT ON project_budget_revisions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION simred_check_revision_applied();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS project_budget_revision_applied_check ON project_budget_revisions;
DROP TRIGGER IF EXISTS project_budget_revisions_history_guard ON project_budget_revisions;
DROP TRIGGER IF EXISTS project_budgets_update_guard ON project_budgets;
DROP TRIGGER IF EXISTS investment_sources_owner_guard ON investment_sources;
DROP TRIGGER IF EXISTS projects_source_institution_guard ON projects;
DROP FUNCTION IF EXISTS simred_check_revision_applied();
DROP FUNCTION IF EXISTS simred_guard_budget_revision();
DROP FUNCTION IF EXISTS simred_guard_budget_update();
DROP FUNCTION IF EXISTS simred_guard_source_owner();
DROP FUNCTION IF EXISTS simred_guard_project_source();
SQL);

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('primary_investment_source_id');
        });
    }
};
