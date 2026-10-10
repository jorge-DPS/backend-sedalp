<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'postgis') AS installed")->installed) {
            throw new RuntimeException('Un administrador debe habilitar PostGIS en esta base antes de ejecutar la migración.');
        }

        Schema::create('region_boundaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('region_id')->unique()->constrained()->restrictOnDelete();
            $table->geometry('geom', subtype: 'multipolygon', srid: 4326);
            $table->string('source_filename');
            $table->unsignedInteger('source_srid');
            $table->jsonb('source_properties');
            $table->timestamps();
            $table->spatialIndex('geom');
        });

        DB::statement('ALTER TABLE region_boundaries ADD CONSTRAINT region_boundaries_geom_valid CHECK (NOT ST_IsEmpty(geom) AND ST_IsValid(geom) AND ST_XMin(Box3D(geom)) >= -180 AND ST_XMax(Box3D(geom)) <= 180 AND ST_YMin(Box3D(geom)) >= -90 AND ST_YMax(Box3D(geom)) <= 90)');
    }

    public function down(): void
    {
        Schema::dropIfExists('region_boundaries');
    }
};
