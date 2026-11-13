<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->integer('start_year');
            $table->integer('end_year');
            $table->timestamps();
            $table->unique(['municipality_id', 'start_year', 'end_year']);
            $table->unique(['id', 'municipality_id']);
        });
        DB::statement('ALTER TABLE municipal_terms ADD CONSTRAINT municipal_terms_year_check CHECK (start_year BETWEEN 1900 AND 2100 AND end_year BETWEEN 1900 AND 2100 AND end_year > start_year)');
        Schema::table('municipal_authorities', function (Blueprint $table): void {
            $table->unsignedBigInteger('municipal_term_id')->nullable()->index();
            $table->foreign(['municipal_term_id', 'municipality_id'], 'municipal_authorities_term_municipality_foreign')
                ->references(['id', 'municipality_id'])->on('municipal_terms')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        DB::statement('LOCK TABLE municipal_terms, municipal_authorities IN ACCESS EXCLUSIVE MODE');
        if (DB::table('municipal_terms')->exists()) {
            throw new RuntimeException('La reversión perdería gestiones municipales registradas.');
        }
        Schema::table('municipal_authorities', function (Blueprint $table): void {
            $table->dropForeign('municipal_authorities_term_municipality_foreign');
            $table->dropColumn('municipal_term_id');
        });
        Schema::drop('municipal_terms');
    }
};
