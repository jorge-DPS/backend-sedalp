<?php

use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\Territory\Municipality;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('revierte y reaplica la publicación manteniendo proyectos presupuestos y territorios privados', function (): void {
    expect(DB::connection()->getDatabaseName())->toBe('sedalp_testing');
    $project = Project::factory()->create(['total_cost_bob' => '1234567890123456.78']);
    $municipality = Municipality::factory()->create();
    $project->municipalities()->attach($municipality);
    $budget = ProjectBudget::factory()->for($project)->reported('0.00')->create();
    $attributes = $project->fresh()->getAttributes();
    unset($attributes['published_at']);
    $migration = require database_path('migrations/2026_11_09_201511_add_published_at_to_projects_table.php');

    for ($cycle = 0; $cycle < 2; $cycle++) {
        $migration->down();
        expect(Schema::hasColumn('projects', 'published_at'))->toBeFalse();
        $migration->up();
        expect($project->fresh()->published_at)->toBeNull()
            ->and($project->fresh()->getAttributes())->toBe([...$attributes, 'published_at' => null])
            ->and($project->municipalities()->sole()->id)->toBe($municipality->id)
            ->and($budget->fresh()->amount_bob)->toBe('0.00');
    }
});

it('bloquea la reversión antes de perder decisiones de publicación registradas', function (): void {
    $project = Project::factory()->published()->create();
    $before = $project->fresh()->getAttributes();
    $migration = require database_path('migrations/2026_11_09_201511_add_published_at_to_projects_table.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'decisiones de publicación');
    expect(Schema::hasColumn('projects', 'published_at'))->toBeTrue()
        ->and($project->fresh()->getAttributes())->toBe($before);
});
