<!-- migracion de Cargo -->

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            // Nombre del cargo institucional.
            // Ej.: Director Técnico, Profesional II.
            $table->string('name', 100)->unique();

            // Descripción opcional del cargo.
            $table->string('description', 150)->nullable();

            // Indica si el cargo continúa vigente.
            $table->boolean('active')->default(true);

            $table->timestamps();
            // softDelete
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
