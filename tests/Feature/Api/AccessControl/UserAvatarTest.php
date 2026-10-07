<?php

use App\Models\People\OrganizationalUnit;
use App\Models\People\Position;
use App\Models\People\Profession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['media.disk' => 'public']);
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $unit = OrganizationalUnit::create(['name' => 'Comunicación', 'code' => 'COM']);
    $position = Position::create(['name' => 'Comunicador']);
    $profession = Profession::create(['name' => 'Comunicación Social']);

    $this->payload = [
        'email' => 'comunicador@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'comunicador',
        'staff_member' => [
            'first_names' => 'María Elena',
            'paternal_surname' => 'Quispe',
            'maternal_surname' => 'Mamani',
            'ci' => '1234567',
            'ci_complement' => '1a',
            'birth_date' => '1992-04-15',
            'phone' => '71234567',
            'email' => 'personal@example.com',
            'organizational_unit_id' => $unit->id,
            'position_id' => $position->id,
            'profession_id' => $profession->id,
        ],
    ];
});

it('crea un comunicador con información completa y avatar optimizado', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->post('/api/admin/users', [
            ...$this->payload,
            'avatar' => UploadedFile::fake()->image('foto.jpg', 800, 600),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.roles.0', 'comunicador')
        ->assertJsonPath('data.staff_member.ci', '1234567')
        ->assertJsonPath('data.staff_member.ci_complement', '1A')
        ->assertJsonPath('data.staff_member.birth_date', '1992-04-15')
        ->assertJsonPath('data.staff_member.phone', '71234567')
        ->assertJsonPath('data.staff_member.email', 'personal@example.com')
        ->assertJsonMissingPath('data.password');

    $user = User::findOrFail($response->json('data.id'));
    $path = User::AVATAR_DIRECTORY.'/'.$user->avatar_filename.'.webp';
    Storage::disk('public')->assertExists($path);
    $response->assertJsonPath('data.avatar_url', Storage::disk('public')->url($path));

    $dimensions = getimagesize(Storage::disk('public')->path($path));
    expect($dimensions[0])->toBe(512)
        ->and($dimensions[1])->toBe(512)
        ->and($dimensions['mime'])->toBe('image/webp');

    $this->getJson('/api/admin/users/'.$user->id)
        ->assertOk()
        ->assertJsonPath('data.avatar_url', $response->json('data.avatar_url'))
        ->assertJsonPath('data.staff_member.ci_complement', '1A');
});

it('permite crear usuarios sin avatar', function () {
    $this->actingAs($this->admin, 'api')
        ->postJson('/api/admin/users', $this->payload)
        ->assertCreated()
        ->assertJsonPath('data.avatar_url', null);

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('reemplaza el avatar y elimina el archivo anterior', function () {
    $user = User::factory()->create();
    $this->actingAs($this->admin, 'api');

    $this->patch('/api/admin/users/'.$user->id, [
        'avatar' => UploadedFile::fake()->image('original.png'),
    ], ['Accept' => 'application/json'])->assertOk();

    $oldFilename = $user->refresh()->avatar_filename;

    $this->patch('/api/admin/users/'.$user->id, [
        'avatar' => UploadedFile::fake()->image('nueva.jpg'),
    ], ['Accept' => 'application/json'])->assertOk();

    $newFilename = $user->refresh()->avatar_filename;
    expect($newFilename)->not->toBe($oldFilename);
    Storage::disk('public')->assertMissing(User::AVATAR_DIRECTORY.'/'.$oldFilename.'.webp');
    Storage::disk('public')->assertExists(User::AVATAR_DIRECTORY.'/'.$newFilename.'.webp');
});

it('conserva el avatar cuando la edición no incluye ese campo y permite quitarlo con null', function () {
    $user = User::factory()->create();
    $this->actingAs($this->admin, 'api');

    $this->patch('/api/admin/users/'.$user->id, [
        'avatar' => UploadedFile::fake()->image('foto.jpg'),
    ], ['Accept' => 'application/json'])->assertOk();
    $filename = $user->refresh()->avatar_filename;
    $path = User::AVATAR_DIRECTORY.'/'.$filename.'.webp';

    $this->patchJson('/api/admin/users/'.$user->id, ['email' => 'editado@example.com'])
        ->assertOk();
    expect($user->refresh()->avatar_filename)->toBe($filename);
    Storage::disk('public')->assertExists($path);

    $this->patchJson('/api/admin/users/'.$user->id, ['avatar' => null])
        ->assertOk()
        ->assertJsonPath('data.avatar_url', null);
    expect($user->refresh()->avatar_filename)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rechaza avatares inválidos sin crear la cuenta ni el personal', function (string $invalidFile) {
    $avatar = match ($invalidFile) {
        'text' => UploadedFile::fake()->create('avatar.txt', 10, 'text/plain'),
        'svg' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        'size' => UploadedFile::fake()->image('foto.jpg')->size(2049),
        'dimensions' => UploadedFile::fake()->image('foto.jpg', 4097, 10),
    };

    $this->actingAs($this->admin, 'api')
        ->post('/api/admin/users', [...$this->payload, 'avatar' => $avatar], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('avatar');

    $this->assertDatabaseMissing('users', ['email' => $this->payload['email']]);
    $this->assertDatabaseCount('staff_members', 0);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with(['text', 'svg', 'size', 'dimensions']);

it('rechaza modificar avatares sin autorización', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor, 'api')
        ->patch('/api/admin/users/'.$target->id, [
            'avatar' => UploadedFile::fake()->image('foto.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertForbidden();

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('protege el avatar de un superadmin aunque el actor pueda editar usuarios', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.update');

    $this->actingAs($actor, 'api')
        ->patch('/api/admin/users/'.$this->admin->id, [
            'avatar' => UploadedFile::fake()->image('foto.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertForbidden();

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('limpia el avatar y revierte el personal si falla la creación de la cuenta', function () {
    $this->withoutExceptionHandling();
    User::creating(function (): void {
        throw new RuntimeException('Fallo de escritura simulado.');
    });

    expect(fn () => $this->actingAs($this->admin, 'api')
        ->post('/api/admin/users', [
            ...$this->payload,
            'avatar' => UploadedFile::fake()->image('foto.jpg'),
        ], ['Accept' => 'application/json']))
        ->toThrow(RuntimeException::class, 'Fallo de escritura simulado.');

    $this->assertDatabaseCount('staff_members', 0);
    $this->assertDatabaseMissing('users', ['email' => $this->payload['email']]);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('conserva el avatar anterior y limpia el nuevo cuando falla la edición', function () {
    $user = User::factory()->create();
    $this->actingAs($this->admin, 'api')
        ->patch('/api/admin/users/'.$user->id, [
            'avatar' => UploadedFile::fake()->image('original.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();
    $filename = $user->refresh()->avatar_filename;

    $this->withoutExceptionHandling();
    User::updating(function (): void {
        throw new RuntimeException('Fallo de actualización simulado.');
    });

    expect(fn () => $this->patch('/api/admin/users/'.$user->id, [
        'avatar' => UploadedFile::fake()->image('nueva.jpg'),
    ], ['Accept' => 'application/json']))
        ->toThrow(RuntimeException::class, 'Fallo de actualización simulado.');

    expect($user->refresh()->avatar_filename)->toBe($filename)
        ->and(Storage::disk('public')->allFiles())
        ->toBe([User::AVATAR_DIRECTORY.'/'.$filename.'.webp']);
});

it('conserva el avatar al enviar el usuario a la papelera y restaurarlo', function () {
    $user = User::factory()->create();
    $this->actingAs($this->admin, 'api')
        ->patch('/api/admin/users/'.$user->id, [
            'avatar' => UploadedFile::fake()->image('foto.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();
    $filename = $user->refresh()->avatar_filename;

    $this->deleteJson('/api/admin/users/'.$user->id, ['reason' => 'Baja de prueba.'])
        ->assertNoContent();
    $this->assertSoftDeleted($user);
    $this->getJson('/api/admin/users/'.$user->id)->assertNotFound();
    Storage::disk('public')->assertExists(User::AVATAR_DIRECTORY.'/'.$filename.'.webp');

    $this->postJson('/api/admin/users/'.$user->id.'/restore', ['reason' => 'Restauración de prueba.'])
        ->assertOk();
    expect($user->refresh()->avatar_filename)->toBe($filename);
});
