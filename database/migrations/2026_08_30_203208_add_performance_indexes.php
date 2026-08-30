<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Necesario para índices trigram.
         *
         * Permite optimizar búsquedas como:
         * ILIKE '%texto%'.
         */
        DB::statement(
            'CREATE EXTENSION IF NOT EXISTS pg_trgm'
        );

        /*
         * Búsqueda administrativa de noticias.
         */
        DB::statement(
            <<<'SQL'
            CREATE INDEX news_title_trgm_idx
            ON news
            USING GIN (title gin_trgm_ops)
            SQL
        );

        DB::statement(
            <<<'SQL'
            CREATE INDEX news_subtitle_trgm_idx
            ON news
            USING GIN (subtitle gin_trgm_ops)
            SQL
        );

        DB::statement(
            <<<'SQL'
            CREATE INDEX news_excerpt_trgm_idx
            ON news
            USING GIN (excerpt gin_trgm_ops)
            SQL
        );

        /*
         * PostgreSQL no crea automáticamente índices
         * sobre las columnas que referencian una FK.
         */
        Schema::table(
            'staff_members',
            function (Blueprint $table): void {
                $table->index(
                    'organizational_unit_id',
                    'staff_members_organizational_unit_id_idx'
                );

                $table->index(
                    'position_id',
                    'staff_members_position_id_idx'
                );

                $table->index(
                    'profession_id',
                    'staff_members_profession_id_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'staff_members',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'staff_members_organizational_unit_id_idx'
                );

                $table->dropIndex(
                    'staff_members_position_id_idx'
                );

                $table->dropIndex(
                    'staff_members_profession_id_idx'
                );
            }
        );

        DB::statement(
            'DROP INDEX IF EXISTS news_title_trgm_idx'
        );

        DB::statement(
            'DROP INDEX IF EXISTS news_subtitle_trgm_idx'
        );

        DB::statement(
            'DROP INDEX IF EXISTS news_excerpt_trgm_idx'
        );

        /*
         * No eliminamos pg_trgm.
         *
         * Puede ser utilizado posteriormente
         * por otros índices o módulos.
         */
    }
};
