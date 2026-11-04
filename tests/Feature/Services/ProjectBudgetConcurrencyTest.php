<?php

use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

it('serializa dos revisiones simultáneas sin duplicar números ni perder el valor previo', function (): void {
    expect(app()->environment('testing'))->toBeTrue()
        ->and(config('database.connections.pgsql.database'))->toBe('sedalp_testing');

    $schema = 'simred_budget_test_'.Str::lower(Str::random(16));
    $originalConnection = DB::getDefaultConnection();
    $configuration = config('database.connections.pgsql');
    $setup = null;
    $processes = [];
    config(['database.connections.budget_concurrency' => $configuration]);
    $admin = DB::connection('budget_concurrency');
    $admin->statement('CREATE SCHEMA "'.$schema.'"');

    try {
        DB::purge('budget_concurrency');
        config(['database.connections.budget_concurrency.search_path' => $schema]);
        DB::setDefaultConnection('budget_concurrency');
        Schema::clearResolvedInstance('db.schema');

        $tables = ['provinces', 'institutions', 'sectors', 'strategic_axes', 'intervention_types', 'project_statuses', 'projects', 'investment_sources', 'project_budgets', 'project_budget_revisions'];

        foreach ($tables as $table) {
            $paths = glob(database_path('migrations/*_create_'.$table.'_table.php'));
            $migration = require $paths[0];
            $migration->up();
        }

        $sourceMigration = require database_path('migrations/2026_11_04_180318_add_primary_investment_source_id_to_projects_table.php');
        $sourceMigration->up();
        $project = Project::factory()->create();
        $budget = ProjectBudget::factory()->reported('100.00')->for($project)->create();
        $setup = DB::connection('budget_concurrency');
        DB::setDefaultConnection($originalConnection);
        Schema::clearResolvedInstance('db.schema');

        $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.pgsql.database') !== 'sedalp_testing') {
    throw new RuntimeException('Solo se permite ejecutar esta prueba en sedalp_testing.');
}
config(['database.connections.pgsql.search_path' => $argv[1]]);
Illuminate\Support\Facades\DB::purge('pgsql');
Illuminate\Support\Facades\DB::select("SELECT set_config('application_name', ?, false)", [$argv[3]]);
$budget = App\Models\Investment\ProjectBudget::findOrFail((int) $argv[2]);
app(App\Services\Investment\ProjectBudgetService::class)->revise($budget, [
    'amount_bob' => $argv[4], 'availability_status' => 'reported',
]);
PHP;

        $setup->beginTransaction();
        $setup->table('project_budgets')->where('id', $budget->id)->lockForUpdate()->first();
        $names = [];

        foreach (['200.00', '300.00'] as $index => $amount) {
            $name = $schema.'_'.$index;
            $names[] = $name;
            $process = new Process([PHP_BINARY, '-r', $worker, $schema, (string) $budget->id, $name, $amount], base_path(), ['APP_ENV' => 'testing', 'PAO_DISABLE' => 'true']);
            $process->setTimeout(20);
            $process->start();
            $processes[] = $process;
        }

        $deadline = microtime(true) + 10;
        $waiting = 0;

        do {
            $setup->select('SELECT pg_stat_clear_snapshot()');
            $waiting = $setup->table('pg_stat_activity')->whereIn('application_name', $names)->where('wait_event_type', 'Lock')->count();

            if ($waiting === 2) {
                break;
            }

            usleep(20000);
        } while (microtime(true) < $deadline);

        $details = implode("\n", array_map(fn (Process $process): string => $process->getErrorOutput().$process->getOutput(), $processes));
        $this->assertSame(2, $waiting, $details);
        $setup->commit();

        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        }

        $revisions = $setup->table('project_budget_revisions')->orderBy('revision_number')->get();
        $current = $setup->table('project_budgets')->where('id', $budget->id)->sole();

        expect($revisions)->toHaveCount(2)
            ->and($revisions->pluck('revision_number')->all())->toBe([1, 2])
            ->and($revisions[0]->previous_amount_bob)->toBe('100.00')
            ->and($revisions[1]->previous_amount_bob)->toBe($revisions[0]->new_amount_bob)
            ->and($current->amount_bob)->toBe($revisions[1]->new_amount_bob)
            ->and($revisions->pluck('new_amount_bob')->sort()->values()->all())->toBe(['200.00', '300.00'])
            ->and($setup->table('project_budgets')->count())->toBe(1);
    } finally {
        if ($setup !== null && $setup->transactionLevel() > 0) {
            $setup->rollBack();
        }

        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(0.2);
            }
        }

        DB::setDefaultConnection($originalConnection);
        Schema::clearResolvedInstance('db.schema');
        DB::purge('budget_concurrency');
        config(['database.connections.budget_concurrency' => $configuration]);

        if (preg_match('/^simred_budget_test_[a-z0-9]{16}$/D', $schema) === 1) {
            DB::connection('budget_concurrency')->statement('DROP SCHEMA "'.$schema.'" CASCADE');
        }

        DB::purge('budget_concurrency');
    }
});
