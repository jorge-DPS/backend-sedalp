<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100)->unique();
            $table->string('traffic_light', 10);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE project_statuses ADD CONSTRAINT project_statuses_traffic_light_check CHECK (traffic_light IN ('green', 'amber', 'red'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('project_statuses');
    }
};
