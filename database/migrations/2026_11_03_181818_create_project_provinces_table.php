<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_provinces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('province_id')->index()->constrained('provinces')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'province_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_provinces');
    }
};
