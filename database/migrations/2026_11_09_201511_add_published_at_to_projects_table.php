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
            $table->timestamp('published_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        DB::statement('LOCK TABLE projects IN ACCESS EXCLUSIVE MODE');
        if (DB::table('projects')->whereNotNull('published_at')->exists()) {
            throw new RuntimeException('No se puede revertir: existen decisiones de publicación que se perderían.');
        }
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('published_at');
        });
    }
};
