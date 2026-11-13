<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('LOCK TABLE municipal_poa_documents IN ACCESS EXCLUSIVE MODE');
        Schema::create('municipal_poa_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipal_poa_id')->constrained()->restrictOnDelete();
            $table->integer('version_number');
            $table->string('document_type', 30);
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['municipal_poa_id', 'version_number']);
        });
        DB::statement("ALTER TABLE municipal_poa_versions ADD CONSTRAINT municipal_poa_versions_number_check CHECK ((version_number = 1 AND document_type = 'initial') OR (version_number > 1 AND document_type = 'reformulated'))");
        DB::statement('ALTER TABLE municipal_poa_versions ADD CONSTRAINT municipal_poa_versions_current_check CHECK (NOT is_current OR published_at IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX municipal_poa_versions_current_unique ON municipal_poa_versions (municipal_poa_id) WHERE is_current');
        Schema::table('municipal_poa_documents', function (Blueprint $table): void {
            $table->foreignId('municipal_poa_version_id')->nullable()->index()->constrained()->restrictOnDelete();
        });
        DB::statement('INSERT INTO municipal_poa_versions (municipal_poa_id, version_number, document_type, published_at, is_current, created_at, updated_at) SELECT municipal_poa_id, version_number, document_type, published_at, is_current, created_at, updated_at FROM municipal_poa_documents ORDER BY id');
        DB::statement('UPDATE municipal_poa_documents d SET municipal_poa_version_id = v.id FROM municipal_poa_versions v WHERE v.municipal_poa_id = d.municipal_poa_id AND v.version_number = d.version_number');
        if (DB::table('municipal_poa_documents as d')->leftJoin('municipal_poa_versions as v', 'v.id', '=', 'd.municipal_poa_version_id')
            ->whereRaw('v.id IS NULL OR ROW(d.municipal_poa_id, d.version_number, d.document_type, d.published_at, d.is_current) IS DISTINCT FROM ROW(v.municipal_poa_id, v.version_number, v.document_type, v.published_at, v.is_current)')->exists()) {
            throw new RuntimeException('No se pudo verificar la conservación de las versiones históricas.');
        }
        DB::statement('ALTER TABLE municipal_poa_documents ALTER COLUMN municipal_poa_version_id SET NOT NULL');
        DB::statement('DROP TRIGGER municipal_poa_documents_history_guard ON municipal_poa_documents');
        DB::statement('ALTER TABLE municipal_poa_documents DROP CONSTRAINT municipal_poa_documents_municipal_poa_id_version_number_unique, DROP CONSTRAINT municipal_poa_documents_version_check, DROP CONSTRAINT municipal_poa_documents_type_check, DROP CONSTRAINT municipal_poa_documents_current_check');
        DB::statement('DROP INDEX municipal_poa_documents_current_unique');
        Schema::table('municipal_poa_documents', function (Blueprint $table): void {
            $table->dropForeign(['municipal_poa_id']);
            $table->dropColumn(['municipal_poa_id', 'version_number', 'document_type', 'published_at', 'is_current']);
        });
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION simred_guard_poa_document_history() RETURNS trigger LANGUAGE plpgsql AS $document$
DECLARE version_published timestamp;
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Los documentos históricos del POA deben conservarse.' USING ERRCODE = '23514';
    END IF;
    SELECT published_at INTO version_published FROM municipal_poa_versions WHERE id = NEW.municipal_poa_version_id FOR UPDATE;
    IF TG_OP = 'INSERT' AND version_published IS NOT NULL THEN
        RAISE EXCEPTION 'No se pueden anexar documentos a una versión publicada.' USING ERRCODE = '23514';
    END IF;
    IF TG_OP = 'UPDATE' THEN
        IF ROW(NEW.municipal_poa_version_id, NEW.pdf_path) IS DISTINCT FROM ROW(OLD.municipal_poa_version_id, OLD.pdf_path) THEN
            RAISE EXCEPTION 'Registre otra versión en lugar de sobrescribir el PDF histórico.' USING ERRCODE = '23514';
        END IF;
        IF version_published IS NOT NULL AND NEW IS DISTINCT FROM OLD THEN
            RAISE EXCEPTION 'Los archivos de una versión publicada son inmutables.' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN NEW;
END;
$document$;
CREATE TRIGGER municipal_poa_documents_history_guard BEFORE INSERT OR UPDATE OR DELETE ON municipal_poa_documents FOR EACH ROW EXECUTE FUNCTION simred_guard_poa_document_history();

CREATE OR REPLACE FUNCTION simred_guard_poa_version_history() RETURNS trigger LANGUAGE plpgsql AS $version$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Las versiones históricas del POA deben conservarse.' USING ERRCODE = '23514';
    END IF;
    IF TG_OP = 'UPDATE' THEN
        IF ROW(NEW.municipal_poa_id, NEW.version_number, NEW.document_type) IS DISTINCT FROM ROW(OLD.municipal_poa_id, OLD.version_number, OLD.document_type) THEN
            RAISE EXCEPTION 'La identidad de una versión es inmutable.' USING ERRCODE = '23514';
        END IF;
        IF OLD.published_at IS NOT NULL AND NEW.published_at IS DISTINCT FROM OLD.published_at THEN
            RAISE EXCEPTION 'La publicación histórica debe conservarse.' USING ERRCODE = '23514';
        END IF;
    END IF;
    IF NEW.published_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM municipal_poa_documents WHERE municipal_poa_version_id = NEW.id) THEN
        RAISE EXCEPTION 'Una versión publicada debe tener documentos.' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$version$;
CREATE TRIGGER municipal_poa_versions_history_guard BEFORE INSERT OR UPDATE OR DELETE ON municipal_poa_versions FOR EACH ROW EXECUTE FUNCTION simred_guard_poa_version_history();
SQL);
    }

    public function down(): void
    {
        DB::statement('LOCK TABLE municipal_poa_versions, municipal_poa_documents IN ACCESS EXCLUSIVE MODE');
        if (DB::table('municipal_poa_versions as v')->leftJoin('municipal_poa_documents as d', 'd.municipal_poa_version_id', '=', 'v.id')
            ->select('v.id')->groupBy('v.id')->havingRaw('COUNT(d.id) <> 1')->exists()) {
            throw new RuntimeException('La reversión perdería versiones vacías o con varios PDF. Conserve el esquema y aplique una migración correctiva.');
        }
        DB::statement('DROP TRIGGER municipal_poa_documents_history_guard ON municipal_poa_documents');
        Schema::table('municipal_poa_documents', function (Blueprint $table): void {
            $table->foreignId('municipal_poa_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('version_number')->nullable();
            $table->string('document_type', 30)->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_current')->default(false);
        });
        DB::statement('UPDATE municipal_poa_documents d SET municipal_poa_id = v.municipal_poa_id, version_number = v.version_number, document_type = v.document_type, published_at = v.published_at, is_current = v.is_current FROM municipal_poa_versions v WHERE v.id = d.municipal_poa_version_id');
        DB::statement('ALTER TABLE municipal_poa_documents ALTER COLUMN municipal_poa_id SET NOT NULL, ALTER COLUMN version_number SET NOT NULL, ALTER COLUMN document_type SET NOT NULL');
        $versionForeignKeys = array_filter(Schema::getForeignKeys('municipal_poa_documents'),
            fn (array $key): bool => $key['columns'] === ['municipal_poa_version_id']);
        Schema::table('municipal_poa_documents', function (Blueprint $table) use ($versionForeignKeys): void {
            foreach ($versionForeignKeys as $key) {
                $table->dropForeign($key['name']);
            }
            $table->dropColumn('municipal_poa_version_id');
            $table->unique(['municipal_poa_id', 'version_number']);
        });
        DB::statement("ALTER TABLE municipal_poa_documents ADD CONSTRAINT municipal_poa_documents_version_check CHECK ((version_number = 1 AND document_type = 'initial') OR (version_number > 1 AND document_type = 'reformulated'))");
        DB::statement("ALTER TABLE municipal_poa_documents ADD CONSTRAINT municipal_poa_documents_type_check CHECK (document_type IN ('initial', 'reformulated'))");
        DB::statement('ALTER TABLE municipal_poa_documents ADD CONSTRAINT municipal_poa_documents_current_check CHECK (NOT is_current OR published_at IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX municipal_poa_documents_current_unique ON municipal_poa_documents (municipal_poa_id) WHERE is_current');
        Schema::drop('municipal_poa_versions');
        DB::statement('DROP FUNCTION IF EXISTS simred_guard_poa_version_history()');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION simred_guard_poa_document_history() RETURNS trigger LANGUAGE plpgsql AS $document$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Los documentos históricos del POA deben conservarse.' USING ERRCODE = '23514';
    END IF;
    IF ROW(NEW.municipal_poa_id, NEW.version_number, NEW.document_type, NEW.pdf_path)
        IS DISTINCT FROM ROW(OLD.municipal_poa_id, OLD.version_number, OLD.document_type, OLD.pdf_path) THEN
        RAISE EXCEPTION 'Registre otra versión en lugar de sobrescribir el PDF histórico.' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$document$;
CREATE TRIGGER municipal_poa_documents_history_guard BEFORE UPDATE OR DELETE ON municipal_poa_documents FOR EACH ROW EXECUTE FUNCTION simred_guard_poa_document_history();
SQL);
    }
};
