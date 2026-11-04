<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->smallInteger('fiscal_year')->index();
            $table->decimal('amount_bob', 18, 2)->nullable();
            $table->string('availability_status', 30);
            $table->foreignId('investment_source_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->text('source_reference')->nullable();
            $table->string('funding_source')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'fiscal_year']);
        });

        DB::statement('ALTER TABLE project_budgets ADD CONSTRAINT project_budgets_year_check CHECK (fiscal_year BETWEEN 1900 AND 2100)');
        DB::statement("ALTER TABLE project_budgets ADD CONSTRAINT project_budgets_amount_check CHECK (amount_bob IS NULL OR (amount_bob >= 0 AND amount_bob <> 'NaN'::numeric))");
        DB::statement("ALTER TABLE project_budgets ADD CONSTRAINT project_budgets_availability_check CHECK ((availability_status = 'reported' AND amount_bob IS NOT NULL) OR (availability_status = 'not_available' AND amount_bob IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budgets');
    }
};
