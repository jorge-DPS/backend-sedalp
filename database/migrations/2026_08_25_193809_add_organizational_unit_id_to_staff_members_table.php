<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_members', function (Blueprint $table) {
            $table->foreignId('organizational_unit_id')
                ->nullable()
                ->constrained('organizational_units')
                ->restrictOnDelete();

            $table->index('organizational_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('staff_members', function (Blueprint $table) {
            $table->dropIndex(['organizational_unit_id']);
            $table->dropConstrainedForeignId('organizational_unit_id');
        });
    }
};
