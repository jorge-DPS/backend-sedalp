<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_poa_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipal_poa_id')->constrained()->restrictOnDelete();
            $table->integer('version_number');
            $table->string('document_type', 30);
            $table->string('title');
            $table->string('document_reference')->nullable();
            $table->string('pdf_path')->unique()->comment('Ruta relativa al disco privado local; no es una URL pública.');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 100);
            $table->bigInteger('file_size_bytes')->nullable();
            $table->date('received_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_current')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['municipal_poa_id', 'version_number']);
        });

        DB::statement("ALTER TABLE municipal_poa_documents ADD CONSTRAINT municipal_poa_documents_version_check CHECK ((version_number = 1 AND document_type = 'initial') OR (version_number > 1 AND document_type = 'reformulated'))");
        DB::statement("ALTER TABLE municipal_poa_documents ADD CONSTRAINT municipal_poa_documents_type_check CHECK (document_type IN ('initial', 'reformulated'))");
        DB::statement("ALTER TABLE municipal_poa_documents ADD CONSTRAINT municipal_poa_documents_file_check CHECK (mime_type = 'application/pdf' AND (file_size_bytes IS NULL OR file_size_bytes >= 0))");
        DB::statement('ALTER TABLE municipal_poa_documents ADD CONSTRAINT municipal_poa_documents_current_check CHECK (NOT is_current OR published_at IS NOT NULL)');
        DB::statement("ALTER TABLE municipal_poa_documents ADD CONSTRAINT municipal_poa_documents_path_check CHECK (pdf_path LIKE 'municipal/poas/%' AND position('..' in pdf_path) = 0 AND position(chr(92) in pdf_path) = 0)");
        DB::statement('CREATE UNIQUE INDEX municipal_poa_documents_current_unique ON municipal_poa_documents (municipal_poa_id) WHERE is_current');

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

    public function down(): void
    {
        Schema::dropIfExists('municipal_poa_documents');
        DB::statement('DROP FUNCTION IF EXISTS simred_guard_poa_document_history()');
    }
};
