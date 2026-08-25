<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('news_id')
                ->constrained('news')
                ->cascadeOnDelete();

            $table->string('filename', 100);

            $table->string('alt', 255);

            $table->text('caption')->nullable();

            $table->unsignedInteger('position');

            $table->timestamps();

            $table->unique('filename');

            $table->unique([
                'news_id',
                'position',
            ]);
        });

        DB::statement("
            ALTER TABLE news_images
            ADD CONSTRAINT news_images_position_check
            CHECK (position >= 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('news_images');
    }
};
