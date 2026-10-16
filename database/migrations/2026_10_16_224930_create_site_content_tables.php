<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 200);
            $table->timestamps();
        });
        Schema::create('site_media', function (Blueprint $table): void {
            $table->id();
            $table->string('profile', 20);
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['disk', 'path']);
            $table->index(['profile', 'id']);
        });
        Schema::create('site_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_page_id')->constrained()->restrictOnDelete();
            $table->string('key', 80);
            $table->string('type', 30);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('eyebrow', 200)->nullable();
            $table->string('title', 500);
            $table->string('subtitle', 1000)->nullable();
            $table->string('description', 10000)->nullable();
            $table->foreignId('desktop_media_id')->nullable()->constrained('site_media')->restrictOnDelete();
            $table->foreignId('mobile_media_id')->nullable()->constrained('site_media')->restrictOnDelete();
            $table->string('image_alt', 500)->nullable();
            $table->jsonb('options')->default('{}');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['site_page_id', 'key']);
            $table->index(['site_page_id', 'position']);
            $table->index('desktop_media_id');
            $table->index('mobile_media_id');
        });
        Schema::create('site_section_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_section_id')->constrained()->restrictOnDelete();
            $table->string('key', 80);
            $table->string('title', 500);
            $table->string('description', 10000);
            $table->string('icon_key', 50)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['site_section_id', 'key']);
            $table->index(['site_section_id', 'position']);
        });
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_position_check CHECK (position >= 0), ADD CONSTRAINT site_sections_type_check CHECK (type IN ('hero', 'content', 'pillars'))");
        DB::statement('ALTER TABLE site_section_items ADD CONSTRAINT site_items_position_check CHECK (position >= 0)');
        DB::statement("ALTER TABLE site_media ADD CONSTRAINT site_media_profile_check CHECK (profile IN ('desktop', 'mobile')), ADD CONSTRAINT site_media_size_check CHECK (size_bytes > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('site_section_items');
        Schema::dropIfExists('site_sections');
        Schema::dropIfExists('site_media');
        Schema::dropIfExists('site_pages');
    }
};
