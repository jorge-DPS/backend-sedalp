<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('sector_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('strategic_axis_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('intervention_type_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('project_status_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('main_province_id')->nullable()->index()
                ->comment('Provincia principal informada por la fuente; no representa todas las provincias beneficiadas.')
                ->constrained('provinces')->restrictOnDelete();
            $table->string('source_code', 50)->nullable()->index();
            $table->text('name');
            $table->string('short_name', 500)->nullable();
            $table->string('executing_unit', 200)->nullable();
            $table->string('cycle_phase', 50)->nullable();
            $table->string('project_condition', 30)->nullable()->comment('new: nuevo; continuing: de continuidad; NULL: sin dato.');
            $table->string('condition_origin', 20)->nullable()->comment('declared: declarada por la fuente; inferred: inferida durante el procesamiento.');
            $table->text('original_status')->nullable();
            $table->string('territorial_scope', 100)->nullable();
            $table->decimal('total_cost_bob', 18, 2)->nullable()->comment('Costo total del proyecto en bolivianos; no es el presupuesto anual del POA.');
            $table->string('funding_source', 255)->nullable();
            $table->text('source_reference')->nullable();
            $table->smallInteger('source_year')->nullable()->index()->comment('Año de la fuente; no implica gestión presupuestaria.');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institution_id', 'source_code', 'source_year']);
        });

        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_total_cost_bob_check CHECK (total_cost_bob >= 0)');
        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_project_condition_check CHECK (project_condition IN ('new', 'continuing'))");
        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_condition_origin_check CHECK (condition_origin IN ('declared', 'inferred'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
