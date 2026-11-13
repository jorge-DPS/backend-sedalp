<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_poa_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('municipal_poa_version_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->bigInteger('expected_size');
            $table->integer('chunk_size');
            $table->string('sha256', 64);
            $table->jsonb('metadata');
            $table->string('status', 20)->default('receiving');
            $table->foreignId('document_id')->nullable()->constrained('municipal_poa_documents')->restrictOnDelete()->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });
        DB::statement("ALTER TABLE municipal_poa_uploads ADD CONSTRAINT municipal_poa_uploads_state_check CHECK (status IN ('receiving', 'queued', 'processing', 'failed', 'completed') AND expected_size > 0 AND chunk_size > 0 AND sha256 ~ '^[a-f0-9]{64}$' AND ((status = 'completed') = (document_id IS NOT NULL)))");
    }

    public function down(): void
    {
        DB::statement('LOCK TABLE municipal_poa_uploads IN ACCESS EXCLUSIVE MODE');
        if (DB::table('municipal_poa_uploads')->exists()) {
            throw new RuntimeException('Conserve o depure explícitamente las sesiones de carga antes de revertir.');
        }
        Schema::drop('municipal_poa_uploads');
    }
};
