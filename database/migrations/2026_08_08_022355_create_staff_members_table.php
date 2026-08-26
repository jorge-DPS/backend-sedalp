<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            /*
            |--------------------------------------------------------------------------
            | Personal information
            |--------------------------------------------------------------------------
            */

            // Uno o varios nombres.
            $table->string('first_names', 100);

            $table->string('paternal_surname', 80)->nullable();

            $table->string('maternal_surname', 80)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Identity document
            |--------------------------------------------------------------------------
            */

            // Cédula de Identidad sin complemento.
            $table->string('ci', 15);

            // Complemento separado.
            // Ej.: 1A, 2B, 1K.
            $table->string('ci_complement', 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Additional information
            |--------------------------------------------------------------------------
            */

            $table->date('birth_date')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Contact information
            |--------------------------------------------------------------------------
            */

            // Ej.: 76543210 o +59176543210.
            $table->string('phone', 20)->nullable();

            $table->string('email', 254)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Position
            |--------------------------------------------------------------------------
            */

            $table->foreignId('position_id')
                ->constrained('positions')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Profession
            |--------------------------------------------------------------------------
            */

            $table->foreignId('profession_id')
                ->constrained('professions')
                ->restrictOnDelete();

            $table->foreignId('organizational_unit_id')
                ->constrained('organizational_units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            // Indica si actualmente forma parte del personal activo.
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'paternal_surname',
                'maternal_surname',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | CI format
        |--------------------------------------------------------------------------
        |
        | El CI debe contener únicamente números.
        |
        */

        DB::statement("
            ALTER TABLE staff_members
            ADD CONSTRAINT staff_members_ci_format_check
            CHECK (ci ~ '^[0-9]{1,15}$')
        ");

        /*
        |--------------------------------------------------------------------------
        | CI complement format
        |--------------------------------------------------------------------------
        |
        | Si existe complemento, debe contener exactamente
        | dos caracteres alfanuméricos en mayúsculas.
        |
        */

        DB::statement("
            ALTER TABLE staff_members
            ADD CONSTRAINT staff_members_ci_complement_format_check
            CHECK (
                ci_complement IS NULL
                OR ci_complement ~ '^[A-Z0-9]{2}$'
            )
        ");

        /*
        |--------------------------------------------------------------------------
        | Unique CI + complement
        |--------------------------------------------------------------------------
        |
        | Evita registrar dos veces exactamente el mismo documento.
        |
        */

        DB::statement("
            CREATE UNIQUE INDEX staff_members_ci_complement_unique
            ON staff_members (
                ci,
                COALESCE(ci_complement, '')
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};
