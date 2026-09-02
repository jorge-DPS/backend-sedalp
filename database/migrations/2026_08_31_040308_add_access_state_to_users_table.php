<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_status', 20)
                ->default('active')
                ->index();

            $table->unsignedInteger('token_version')
                ->default(1);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_account_status_check
            CHECK (account_status IN ('active', 'suspended'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_token_version_check
            CHECK (token_version >= 1)
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE users DROP CONSTRAINT IF EXISTS users_account_status_check'
        );

        DB::statement(
            'ALTER TABLE users DROP CONSTRAINT IF EXISTS users_token_version_check'
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['account_status']);
            $table->dropColumn([
                'account_status',
                'token_version',
            ]);
        });
    }
};
