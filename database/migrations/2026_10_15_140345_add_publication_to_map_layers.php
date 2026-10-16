<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('map_layer_versions', function (Blueprint $table): void {
            $table->unique(['map_layer_id', 'id'], 'map_versions_layer_id_unique');
        });
        Schema::table('map_layers', function (Blueprint $table): void {
            $table->string('category', 20)->default('other')->index();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreign(['id', 'published_version_id'], 'map_layers_published_version_foreign')
                ->references(['map_layer_id', 'id'])->on('map_layer_versions')->restrictOnDelete();
        });
        DB::table('map_layers')->where('kind', 'boundary')->update(['category' => 'boundaries']);
        DB::statement("ALTER TABLE map_layers ADD CONSTRAINT map_layers_category_check CHECK (
            (kind = 'boundary' AND category = 'boundaries') OR
            (kind = 'overlay' AND category IN ('roads','rivers','cableways','other'))
        )");
        DB::statement('ALTER TABLE map_layers ADD CONSTRAINT map_layers_publication_check CHECK (
            (published_version_id IS NULL AND published_at IS NULL AND published_by IS NULL) OR
            (published_version_id IS NOT NULL AND published_at IS NOT NULL AND published_by IS NOT NULL)
        )');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE map_layers DROP CONSTRAINT map_layers_publication_check');
        DB::statement('ALTER TABLE map_layers DROP CONSTRAINT map_layers_category_check');
        Schema::table('map_layers', function (Blueprint $table): void {
            $table->dropForeign('map_layers_published_version_foreign');
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn(['category', 'published_version_id', 'published_at']);
        });
        Schema::table('map_layer_versions', function (Blueprint $table): void {
            $table->dropUnique('map_versions_layer_id_unique');
        });
    }
};
