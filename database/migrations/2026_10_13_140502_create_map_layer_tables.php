<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_layers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 20)->default('overlay');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['region_id', 'name']);
        });
        DB::statement("ALTER TABLE map_layers ADD CONSTRAINT map_layers_kind_check CHECK (kind IN ('boundary', 'overlay') AND (kind <> 'boundary' OR region_id IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX map_layers_region_boundary_unique ON map_layers (region_id) WHERE kind = 'boundary'");

        Schema::create('map_layer_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('map_layer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('source_filename');
            $table->string('source_path');
            $table->char('sha256', 64);
            $table->jsonb('summary');
            $table->timestamps();
            $table->unique(['map_layer_id', 'number']);
        });

        Schema::create('map_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('map_layer_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->jsonb('properties');
            $table->geometry('geom', subtype: 'geometry', srid: 4326);
            $table->unique(['map_layer_version_id', 'position']);
            $table->spatialIndex('geom');
        });
        DB::statement("ALTER TABLE map_features ADD CONSTRAINT map_features_geom_check CHECK (ST_GeometryType(geom) IN ('ST_Point','ST_MultiPoint','ST_LineString','ST_MultiLineString','ST_Polygon','ST_MultiPolygon') AND NOT ST_IsEmpty(geom) AND ST_IsValid(geom) AND ST_XMin(Box3D(geom)) >= -180 AND ST_XMax(Box3D(geom)) <= 180 AND ST_YMin(Box3D(geom)) >= -90 AND ST_YMax(Box3D(geom)) <= 90)");
    }

    public function down(): void
    {
        Schema::dropIfExists('map_features');
        Schema::dropIfExists('map_layer_versions');
        Schema::dropIfExists('map_layers');
    }
};
