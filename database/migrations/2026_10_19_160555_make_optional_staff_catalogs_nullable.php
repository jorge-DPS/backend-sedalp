<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_members', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizational_unit_id')->nullable()->change();
            $table->unsignedBigInteger('profession_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('staff_members')->whereNull('organizational_unit_id')->orWhereNull('profession_id')->exists()) {
            throw new RuntimeException('Complete las unidades y profesiones del personal antes de revertir esta migración.');
        }
        Schema::table('staff_members', function (Blueprint $table): void {
            $table->unsignedBigInteger('organizational_unit_id')->nullable(false)->change();
            $table->unsignedBigInteger('profession_id')->nullable(false)->change();
        });
    }
};
