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
        Schema::table('users', function (Blueprint $table) {
            //
            /*
            |--------------------------------------------------------------------------
            | Staff member relationship
            |--------------------------------------------------------------------------
            |
            | Un usuario puede estar relacionado con un miembro del personal.
            |
            | unique() garantiza que una persona no tenga dos cuentas.
            |
            | nullable() permite tener cuentas especiales, como el superusuario
            | creado mediante Seeder, que podrían no representar a una persona.
            |
            */

            $table->foreignId('staff_member_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('staff_members')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Soft Deletes
            |--------------------------------------------------------------------------
            |
            | Al eliminar un usuario no se elimina físicamente.
            |
            */

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['staff_member_id']);
                $table->dropUnique(['staff_member_id']);
                $table->dropColumn('staff_member_id');

                $table->dropSoftDeletes();
            });
        });
    }
};
