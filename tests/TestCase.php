<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function migrateDatabases(): void
    {
        if (! $this->app->environment('testing') || DB::connection()->getDriverName() !== 'pgsql'
            || DB::connection()->getDatabaseName() !== 'sedalp_testing') {
            throw new RuntimeException('Las pruebas con transacciones requieren PostgreSQL sedalp_testing.');
        }

        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }
}
