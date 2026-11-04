<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_poas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->smallInteger('fiscal_year')->index()->comment('Gestión fiscal entre 1900 y 2100.');
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['municipality_id', 'fiscal_year']);
        });

        DB::statement('ALTER TABLE municipal_poas ADD CONSTRAINT municipal_poas_year_check CHECK (fiscal_year BETWEEN 1900 AND 2100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_poas');
    }
};
