<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_videos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('news_id')
                ->constrained('news')
                ->cascadeOnDelete();

            $table->string('youtube_url', 2048);

            $table->string('title', 255);

            $table->integer('position');

            $table->timestamps();

            $table->unique([
                'news_id',
                'position',
            ]);
        });

        DB::statement('
            ALTER TABLE news_videos
            ADD CONSTRAINT news_videos_position_check
            CHECK (position >= 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('news_videos');
    }
};
