<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('slug', 255)
                ->unique();

            $table->string('title', 255);

            $table->string('subtitle', 255)
                ->nullable();

            $table->text('excerpt');

            $table->text('description');

            /*
             * Documento generado por TipTap.
             */
            $table->jsonb('content');

            /*
             * Puede ser NULL mientras la noticia
             * no esté publicada.
             *
             * PostgreSQL impedirá que una noticia
             * con status = published tenga esta
             * columna en NULL.
             */
            $table->date('published_at')
                ->nullable();

            $table->string('status', 20)
                ->default('draft');

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'status',
                'published_at',
            ]);

            $table->index('created_by');
            $table->index('updated_by');
        });

        /*
         * Solo se permiten los estados definidos
         * por el dominio de noticias.
         */
        DB::statement(
            <<<'SQL'
            ALTER TABLE news
            ADD CONSTRAINT news_status_check
            CHECK (
                status IN (
                    'draft',
                    'published',
                    'archived'
                )
            )
            SQL
        );

        /*
         * Invariante de publicación:
         *
         * Una noticia publicada siempre debe tener
         * una fecha de publicación.
         *
         * draft     + NULL  = válido
         * archived  + NULL  = válido
         * published + fecha = válido
         * published + NULL  = inválido
         */
        DB::statement(
            <<<'SQL'
            ALTER TABLE news
            ADD CONSTRAINT news_published_requires_published_at
            CHECK (
                status <> 'published'
                OR published_at IS NOT NULL
            )
            SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
