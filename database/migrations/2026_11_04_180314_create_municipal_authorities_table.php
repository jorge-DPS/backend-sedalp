<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_authorities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->string('full_name', 200);
            $table->string('role', 30);
            $table->string('position_title', 150)->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            $table->boolean('is_current')->default(false);
            $table->boolean('is_published')->default(false);
            $table->text('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['municipality_id', 'role', 'is_current']);
            $table->index(['municipality_id', 'is_published']);
        });

        DB::statement("ALTER TABLE municipal_authorities ADD CONSTRAINT municipal_authorities_role_check CHECK (role IN ('mayor', 'council_member'))");
        DB::statement('ALTER TABLE municipal_authorities ADD CONSTRAINT municipal_authorities_term_check CHECK (term_start IS NULL OR term_end IS NULL OR term_end >= term_start)');
        DB::statement("CREATE UNIQUE INDEX municipal_authorities_current_mayor_unique ON municipal_authorities (municipality_id) WHERE role = 'mayor' AND is_current");
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_authorities');
    }
};
