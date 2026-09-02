<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_state_changes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('actor_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('target_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('staff_member_id')
                ->nullable()
                ->constrained('staff_members')
                ->restrictOnDelete();

            $table->string('action', 40);
            $table->string('reason', 1000);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index([
                'target_user_id',
                'created_at',
            ]);

            $table->index([
                'staff_member_id',
                'created_at',
            ]);

            $table->index([
                'action',
                'created_at',
            ]);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE access_state_changes
            ADD CONSTRAINT access_state_changes_subject_check
            CHECK (target_user_id IS NOT NULL OR staff_member_id IS NOT NULL)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE access_state_changes
            ADD CONSTRAINT access_state_changes_action_check
            CHECK (action IN (
                'user_activated',
                'user_suspended',
                'user_deleted',
                'user_restored',
                'user_credentials_updated',
                'staff_activated',
                'staff_deactivated'
            ))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('access_state_changes');
    }
};
