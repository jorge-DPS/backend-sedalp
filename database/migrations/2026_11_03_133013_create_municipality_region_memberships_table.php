<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'btree_gist') AS installed")->installed) {
            throw new RuntimeException('Un administrador debe habilitar btree_gist en esta base antes de ejecutar la migración.');
        }

        Schema::create('municipality_region_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->date('valid_from')->comment('Inicio del intervalo [valid_from, valid_to). En baseline es el inicio conocido del historial, no la fecha oficial de incorporación.');
            $table->date('valid_to')->nullable();
            $table->string('origin_type', 20);
            $table->string('agreement_reference', 255)->nullable();
            $table->date('agreement_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['municipality_id', 'valid_from'], 'municipality_memberships_municipality_dates_index');
            $table->index(['region_id', 'valid_from', 'valid_to'], 'municipality_memberships_region_dates_index');
        });

        DB::statement('ALTER TABLE municipality_region_memberships ADD CONSTRAINT municipality_memberships_dates_check CHECK (valid_to IS NULL OR valid_to > valid_from)');
        DB::statement("ALTER TABLE municipality_region_memberships ADD CONSTRAINT municipality_memberships_origin_check CHECK (origin_type IN ('baseline', 'agreement'))");
        DB::statement("ALTER TABLE municipality_region_memberships ADD CONSTRAINT municipality_memberships_agreement_check CHECK (origin_type <> 'agreement' OR (agreement_reference IS NOT NULL AND btrim(agreement_reference) <> ''))");
        DB::statement("ALTER TABLE municipality_region_memberships ADD CONSTRAINT municipality_memberships_no_overlap EXCLUDE USING gist (municipality_id WITH =, daterange(valid_from, valid_to, '[)') WITH &&)");
        DB::statement('CREATE UNIQUE INDEX municipality_memberships_open_unique ON municipality_region_memberships (municipality_id) WHERE valid_to IS NULL');
        DB::statement("CREATE UNIQUE INDEX municipality_memberships_baseline_unique ON municipality_region_memberships (municipality_id) WHERE origin_type = 'baseline'");
    }

    public function down(): void
    {
        Schema::dropIfExists('municipality_region_memberships');
    }
};
