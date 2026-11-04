<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_budget_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_budget_id')->constrained()->restrictOnDelete();
            $table->integer('revision_number');
            $table->decimal('previous_amount_bob', 18, 2)->nullable();
            $table->decimal('new_amount_bob', 18, 2)->nullable();
            $table->string('previous_availability_status', 30);
            $table->string('new_availability_status', 30);
            $table->foreignId('investment_source_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->text('source_reference')->nullable();
            $table->date('effective_date')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['project_budget_id', 'revision_number']);
            $table->index(['project_budget_id', 'effective_date']);
        });

        DB::statement('ALTER TABLE project_budget_revisions ADD CONSTRAINT project_budget_revisions_number_check CHECK (revision_number >= 1)');
        DB::statement("ALTER TABLE project_budget_revisions ADD CONSTRAINT project_budget_revisions_amount_check CHECK ((previous_amount_bob IS NULL OR (previous_amount_bob >= 0 AND previous_amount_bob <> 'NaN'::numeric)) AND (new_amount_bob IS NULL OR (new_amount_bob >= 0 AND new_amount_bob <> 'NaN'::numeric)))");
        DB::statement("ALTER TABLE project_budget_revisions ADD CONSTRAINT project_budget_revisions_previous_check CHECK ((previous_availability_status = 'reported' AND previous_amount_bob IS NOT NULL) OR (previous_availability_status = 'not_available' AND previous_amount_bob IS NULL))");
        DB::statement("ALTER TABLE project_budget_revisions ADD CONSTRAINT project_budget_revisions_new_check CHECK ((new_availability_status = 'reported' AND new_amount_bob IS NOT NULL) OR (new_availability_status = 'not_available' AND new_amount_bob IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budget_revisions');
    }
};
