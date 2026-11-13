<?php

use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalTerm;
use App\Models\Territory\Municipality;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('tecnico');
    $this->actingAs($this->user, 'api');
    $this->municipality = Municipality::factory()->create();
    $this->base = '/api/admin/municipalities/'.$this->municipality->id;
    $this->url = $this->base.'/terms';
});

it('registra varias gestiones valida años y duplicados sin prohibir superposiciones no confirmadas', function (): void {
    $id = $this->postJson($this->url, ['start_year' => 2020, 'end_year' => 2025])->assertCreated()->assertJsonPath('data.label', 'Gestión 2020–2025')->json('data.id');
    $this->postJson($this->url, ['start_year' => 2025, 'end_year' => 2030])->assertCreated();
    $this->postJson($this->url, ['start_year' => 2024, 'end_year' => 2029])->assertCreated();
    $this->postJson($this->url, ['start_year' => 2020, 'end_year' => 2025])->assertUnprocessable();
    $this->postJson($this->url, ['start_year' => 2025, 'end_year' => 2025])->assertUnprocessable();
    $this->postJson($this->url, ['start_year' => 1899, 'end_year' => 2025])->assertUnprocessable();
    $this->patchJson($this->url.'/'.$id, ['end_year' => 2026])->assertOk()->assertJsonPath('data.end_year', 2026);
    $this->getJson($this->url.'?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
});

it('agrupa alcaldes y concejales y conserva fechas individuales al realizar una transición', function (): void {
    $term = MunicipalTerm::factory()->for($this->municipality)->create(['start_year' => 2020, 'end_year' => 2030]);
    $authoritiesUrl = $this->url.'/'.$term->id.'/authorities';
    $first = $this->postJson($authoritiesUrl, [
        'full_name' => 'Alcalde anterior', 'role' => 'mayor', 'is_current' => true, 'term_start' => today()->subYears(2)->toDateString(),
    ])->assertCreated()->assertJsonPath('data.municipal_term_id', $term->id)->json('data.id');
    $council = [];
    foreach (['Concejal uno', 'Concejal dos'] as $name) {
        $id = $this->postJson($authoritiesUrl, ['full_name' => $name, 'role' => 'council_member', 'is_current' => true,
            'term_start' => today()->subYear()->toDateString()])->assertCreated()->json('data.id');
        $council[$id] = MunicipalAuthority::findOrFail($id)->getAttributes();
    }
    $second = $this->postJson($authoritiesUrl, [
        'full_name' => 'Alcalde entrante', 'role' => 'mayor', 'term_start' => today()->toDateString(),
    ])->assertCreated()->json('data.id');
    $this->putJson($this->base.'/authorities/'.$second.'/current', [
        'previous_authority_id' => $first, 'previous_term_end' => today()->subDay()->toDateString(),
    ])->assertOk()->assertJsonPath('data.is_current', true)->assertJsonPath('data.is_published', false);
    foreach ($council as $id => $attributes) {
        expect(MunicipalAuthority::findOrFail($id)->getAttributes())->toBe($attributes);
    }
    expect($term->municipalAuthorities()->where('role', 'mayor')->count())->toBe(2)
        ->and(MunicipalAuthority::findOrFail($first)->term_end->toDateString())->toBe(today()->subDay()->toDateString());
    $this->getJson($authoritiesUrl)->assertOk()->assertJsonCount(4, 'data');
    $this->patchJson($this->url.'/'.$term->id, ['end_year' => 2031])->assertUnprocessable();
});

it('exige gestión para registros nuevos y permite asociar históricos sin adivinar sus fechas', function (): void {
    $this->postJson($this->base.'/authorities', ['full_name' => 'Sin gestión', 'role' => 'council_member'])
        ->assertUnprocessable()->assertJsonValidationErrors('municipal_term_id');
    $legacy = MunicipalAuthority::factory()->for($this->municipality)->create(['term_start' => null, 'term_end' => null]);
    $term = MunicipalTerm::factory()->for($this->municipality)->create();
    $this->getJson($this->base.'/authorities/'.$legacy->id)->assertOk()->assertJsonPath('data.municipal_term_id', null);
    $this->patchJson($this->base.'/authorities/'.$legacy->id, ['municipal_term_id' => $term->id])->assertOk()
        ->assertJsonPath('data.municipal_term_id', $term->id)->assertJsonPath('data.term_start', null)->assertJsonPath('data.term_end', null);
});

it('rechaza cruces de municipio y gestión tanto en API como en claves foráneas', function (): void {
    $term = MunicipalTerm::factory()->for($this->municipality)->create();
    $other = MunicipalTerm::factory()->create();
    $otherLocal = MunicipalTerm::factory()->for($this->municipality)->create(['start_year' => 1980, 'end_year' => 1985]);
    $authority = MunicipalAuthority::factory()->for($this->municipality)->create(['municipal_term_id' => $term->id]);
    $this->postJson($this->base.'/authorities', ['full_name' => 'Cruce', 'role' => 'mayor', 'municipal_term_id' => $other->id])->assertUnprocessable();
    $this->getJson($this->url.'/'.$other->id)->assertNotFound();
    $this->getJson($this->url.'/'.$otherLocal->id.'/authorities/'.$authority->id)->assertNotFound();
    $this->patchJson($this->url.'/'.$otherLocal->id.'/authorities/'.$authority->id, ['notes' => 'Cruce'])->assertNotFound();
    $this->patchJson($this->url.'/'.$term->id.'/authorities/'.$authority->id, ['municipal_term_id' => $otherLocal->id])->assertUnprocessable();
    expect(fn () => DB::transaction(fn () => $authority->update(['municipal_term_id' => $other->id])))->toThrow(QueryException::class);
});

it('filtra por gestión y conserva el alcalde actual único para todo el municipio', function (): void {
    $first = MunicipalTerm::factory()->for($this->municipality)->create(['start_year' => 2000, 'end_year' => 2005]);
    $second = MunicipalTerm::factory()->for($this->municipality)->create(['start_year' => 2005, 'end_year' => 2010]);
    MunicipalAuthority::factory()->for($this->municipality)->mayor()->current()->create(['municipal_term_id' => $first->id]);
    $historical = MunicipalAuthority::factory()->for($this->municipality)->create(['municipal_term_id' => $second->id]);
    $this->getJson($this->base.'/authorities?municipal_term_id='.$second->id)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $historical->id);
    $this->postJson($this->base.'/authorities', [
        'full_name' => 'Segundo actual', 'role' => 'mayor', 'is_current' => true, 'municipal_term_id' => $second->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('is_current');
    expect(fn () => DB::transaction(fn () => MunicipalAuthority::factory()->for($this->municipality)->mayor()->current()->create(['municipal_term_id' => $second->id])))->toThrow(QueryException::class);
});

it('publica autoridades de una gestión sin revelar actores privados ni notas', function (): void {
    $term = MunicipalTerm::factory()->for($this->municipality)->create();
    $privateTerm = MunicipalTerm::factory()->for($this->municipality)->create(['start_year' => 1980, 'end_year' => 1985]);
    $published = MunicipalAuthority::factory()->for($this->municipality)->published()->create(['municipal_term_id' => $term->id, 'notes' => 'Interno']);
    $private = MunicipalAuthority::factory()->for($this->municipality)->current()->create(['municipal_term_id' => $term->id]);
    MunicipalAuthority::factory()->for($this->municipality)->create(['municipal_term_id' => $privateTerm->id]);
    $publicUrl = str_replace('/admin/', '/public/', $this->url);
    $this->getJson($publicUrl)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.authorities_count', 1);
    $this->getJson($publicUrl.'/'.$privateTerm->id)->assertNotFound();
    $this->getJson($publicUrl.'/'.$term->id.'/authorities')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id)->assertJsonPath('data.0.is_current', false)->assertJsonMissingPath('data.0.notes');
    $this->getJson($publicUrl.'/'.$term->id.'/authorities/'.$private->id)->assertNotFound();
    $this->getJson(str_replace('/admin/', '/public/', $this->base).'/authorities?municipal_term_id='.$term->id)
        ->assertOk()->assertJsonCount(1, 'data');
});

it('reutiliza permisos existentes sin conceder administración de gestiones al lector', function (): void {
    $term = MunicipalTerm::factory()->for($this->municipality)->create();
    $reader = User::factory()->create();
    $reader->assignRole('director');
    $this->actingAs($reader, 'api')->getJson($this->url.'/'.$term->id)->assertOk();
    $this->postJson($this->url, ['start_year' => 2020, 'end_year' => 2025])->assertForbidden();
    $this->patchJson($this->url.'/'.$term->id, ['end_year' => 2030])->assertForbidden();
    $this->deleteJson($this->url.'/'.$term->id)->assertMethodNotAllowed();
});
