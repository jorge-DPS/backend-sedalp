<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->index()->constrained()->restrictOnDelete();
            $table->string('provided_by_name', 200)->nullable();
            $table->string('source_type', 50);
            $table->string('title');
            $table->smallInteger('fiscal_year')->nullable()->index();
            $table->string('document_reference')->nullable();
            $table->string('version_label', 100)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->text('source_url')->nullable();
            $table->date('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE investment_sources ADD CONSTRAINT investment_sources_year_check CHECK (fiscal_year IS NULL OR fiscal_year BETWEEN 1900 AND 2100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_sources');
    }
};
