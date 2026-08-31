<?php

use App\Models\People\OrganizationalUnit;
use App\Models\People\Position;
use App\Models\People\Profession;
use App\Models\People\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

function loginAndGetToken(
    string $email = 'usuario@test.com',
    string $password = 'Password123!'
): string {
    User::factory()->create([
        'email' => $email,
        'password' => $password,
    ]);

    $response = test()->postJson('/api/auth/login', [
        'email' => $email,
        'password' => $password,
    ]);

    $response->assertOk();

    return $response->json('access_token');
}

function createStaffForAuthTest(
    bool $active = true,
    string $suffix = '01'
): StaffMember {
    $organizationalUnit = OrganizationalUnit::create([
        'name' => "Unidad Auth {$suffix}",
        'code' => "AUTH_{$suffix}",
        'description' => 'Unidad para pruebas de autenticación.',
        'active' => true,
    ]);

    $position = Position::create([
        'name' => "Cargo Auth {$suffix}",
        'description' => 'Cargo para pruebas de autenticación.',
        'active' => true,
    ]);

    $profession = Profession::create([
        'name' => "Profesión Auth {$suffix}",
        'active' => true,
    ]);

    return StaffMember::create([
        'first_names' => 'Usuario Auth',
        'paternal_surname' => 'Prueba',
        'maternal_surname' => null,
        'birth_date' => '1990-01-01',
        'ci' => "1000000{$suffix}",
        'ci_complement' => null,
        'phone' => null,
        'email' => null,
        'position_id' => $position->id,
        'profession_id' => $profession->id,
        'organizational_unit_id' => $organizationalUnit->id,
        'active' => $active,
    ]);
}

it('rechaza login sin credenciales', function () {
    $this->postJson('/api/auth/login')
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
            'password',
        ]);
});

it('rechaza credenciales incorrectas', function () {
    User::factory()->create([
        'email' => 'usuario@test.com',
        'password' => 'Password123!',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'usuario@test.com',
        'password' => 'Incorrecta123!',
    ])
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Credenciales incorrectas.',
        ]);
});

it('permite iniciar sesión con credenciales correctas', function () {
    User::factory()->create([
        'email' => 'usuario@test.com',
        'password' => 'Password123!',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'usuario@test.com',
        'password' => 'Password123!',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'Inicio de sesión exitoso.',
            'token_type' => 'bearer',
        ])
        ->assertJsonStructure([
            'message',
            'access_token',
            'token_type',
            'expires_in',
        ]);

    expect($response->json('access_token'))
        ->toBeString()
        ->not->toBeEmpty();

    expect($response->json('expires_in'))
        ->toBeInt()
        ->toBeGreaterThan(0);
});

it('permite login a una cuenta técnica sin personal asociado', function () {
    $user = User::factory()->create([
        'email' => 'tecnico.auth@test.com',
        'password' => 'Password123!',
        'staff_member_id' => null,
    ]);

    expect($user->staff_member_id)
        ->toBeNull();

    $this->postJson('/api/auth/login', [
        'email' => 'tecnico.auth@test.com',
        'password' => 'Password123!',
    ])
        ->assertOk()
        ->assertJson([
            'message' => 'Inicio de sesión exitoso.',
            'token_type' => 'bearer',
        ])
        ->assertJsonStructure([
            'access_token',
            'expires_in',
        ]);
});

it('permite login cuando el personal asociado está activo', function () {
    $staffMember = createStaffForAuthTest(
        active: true,
        suffix: '11'
    );

    User::factory()->create([
        'staff_member_id' => $staffMember->id,
        'email' => 'personal.activo@test.com',
        'password' => 'Password123!',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'personal.activo@test.com',
        'password' => 'Password123!',
    ])
        ->assertOk()
        ->assertJson([
            'message' => 'Inicio de sesión exitoso.',
            'token_type' => 'bearer',
        ])
        ->assertJsonStructure([
            'access_token',
            'expires_in',
        ]);
});

it('rechaza login cuando el personal asociado está inactivo', function () {
    $staffMember = createStaffForAuthTest(
        active: false,
        suffix: '12'
    );

    User::factory()->create([
        'staff_member_id' => $staffMember->id,
        'email' => 'personal.inactivo@test.com',
        'password' => 'Password123!',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'personal.inactivo@test.com',
        'password' => 'Password123!',
    ])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Cuenta inhabilitada.',
        ]);
});

it('rechaza login cuando el personal asociado está eliminado', function () {
    $staffMember = createStaffForAuthTest(
        active: true,
        suffix: '13'
    );

    User::factory()->create([
        'staff_member_id' => $staffMember->id,
        'email' => 'personal.eliminado@test.com',
        'password' => 'Password123!',
    ]);

    /*
     * El soft delete conserva la FK.
     * La cuenta continúa existiendo, pero ya no
     * debe poder autenticarse.
     */
    $staffMember->delete();

    $this->assertSoftDeleted('staff_members', [
        'id' => $staffMember->id,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'personal.eliminado@test.com',
        'password' => 'Password123!',
    ])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Cuenta inhabilitada.',
        ]);
});

it('rechaza me sin token', function () {
    $this->getJson('/api/auth/me')
        ->assertUnauthorized();
});

it('devuelve el usuario autenticado en me', function () {
    $token = loginAndGetToken();

    $response = $this
        ->withToken($token)
        ->getJson('/api/auth/me');

    $response
        ->assertOk()
        ->assertJsonPath(
            'user.email',
            'usuario@test.com'
        )
        ->assertJsonStructure([
            'user' => [
                'id',
                'email',
            ],
            'authorization' => [
                'roles',
                'permissions',
            ],
        ]);

    expect($response->json('authorization.roles'))
        ->toBeArray();

    expect($response->json('authorization.permissions'))
        ->toBeArray();
});

it('permite cerrar sesión', function () {
    $token = loginAndGetToken();

    $this
        ->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJson([
            'message' => 'Sesión cerrada correctamente.',
        ]);
});

it('invalida el token después de logout', function () {
    $token = loginAndGetToken();

    $this
        ->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertOk();

    $this
        ->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();
});

it('permite renovar el token', function () {
    $token = loginAndGetToken();

    $response = $this
        ->withToken($token)
        ->postJson('/api/auth/refresh');

    $response
        ->assertOk()
        ->assertJson([
            'token_type' => 'bearer',
        ])
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);

    expect($response->json('access_token'))
        ->toBeString()
        ->not->toBeEmpty();
});

it('permite refrescar un token expirado dentro del refresh ttl', function () {
    $user = User::factory()->create([
        'email' => 'refresh.expirado@test.com',
        'password' => 'Password123!',
        'staff_member_id' => null,
    ]);

    /*
     * Creamos un access token que dura solamente
     * un minuto.
     */
    $token = auth('api')
        ->setTTL(1)
        ->login($user);

    /*
     * Avanzamos dos minutos.
     *
     * El access token ya está expirado,
     * pero todavía debería estar dentro
     * de refresh_ttl.
     */
    $this->travel(2)->minutes();

    $response = $this
        ->withToken($token)
        ->postJson('/api/auth/refresh');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);

    $newToken = $response->json('access_token');

    expect($newToken)
        ->not
        ->toBe($token);
});

it('invalida el token anterior después de refrescarlo', function () {
    $user = User::factory()->create([
        'email' => 'refresh.blacklist@test.com',
        'password' => 'Password123!',
        'staff_member_id' => null,
    ]);

    $loginResponse = $this->postJson(
        '/api/auth/login',
        [
            'email' => $user->email,
            'password' => 'Password123!',
        ]
    );

    $loginResponse->assertOk();

    $oldToken = $loginResponse->json(
        'access_token'
    );

    /*
     * Simulamos el final real de la petición.
     *
     * unsetToken() limpia el JWT interno.
     * forgetGuards() limpia el guard resuelto.
     */
    auth('api')->unsetToken();
    Auth::forgetGuards();

    $refreshResponse = $this
        ->withToken($oldToken)
        ->postJson('/api/auth/refresh');

    $refreshResponse->assertOk();

    $newToken = $refreshResponse->json(
        'access_token'
    );

    expect($newToken)
        ->not
        ->toBe($oldToken);

    /*
     * Nueva petición independiente.
     */
    auth('api')->unsetToken();
    Auth::forgetGuards();

    /*
     * El viejo debe estar en blacklist.
     */
    $this
        ->withToken($oldToken)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    /*
     * Nueva petición independiente.
     */
    auth('api')->unsetToken();
    Auth::forgetGuards();

    /*
     * El nuevo sí debe funcionar.
     */
    $this
        ->withToken($newToken)
        ->getJson('/api/auth/me')
        ->assertOk();
});

it('rechaza refrescar un token fuera del refresh ttl', function () {
    /*
     * Access token:
     * 1 minuto.
     *
     * Ventana máxima de refresh:
     * 2 minutos desde la emisión.
     */
    config([
        'jwt.ttl' => 1,
        'jwt.refresh_ttl' => 2,
    ]);

    $user = User::factory()->create([
        'email' => 'refresh.vencido@test.com',
        'password' => 'Password123!',
        'staff_member_id' => null,
    ]);

    $token = auth('api')
        ->setTTL(1)
        ->login($user);

    /*
     * Avanzamos más allá de refresh_ttl.
     */
    $this->travel(3)->minutes();

    $this
        ->withToken($token)
        ->postJson('/api/auth/refresh')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'El token no puede ser renovado.',
        ]);
});

it('rechaza un token existente cuando el personal es desactivado', function () {
    $staffMember = createStaffForAuthTest(
        active: true,
        suffix: '21'
    );

    $user = User::factory()->create([
        'staff_member_id' => $staffMember->id,
        'email' => 'personal.desactivado@test.com',
        'password' => 'Password123!',
    ]);

    $response = $this->postJson(
        '/api/auth/login',
        [
            'email' => $user->email,
            'password' => 'Password123!',
        ]
    );

    $response->assertOk();

    $token = $response->json(
        'access_token'
    );

    auth('api')->unsetToken();
    Auth::forgetGuards();

    /*
     * Terminamos el contexto de autenticación
     * de la petición de login.
     */
    Auth::forgetGuards();

    /*
     * El administrador desactiva al personal
     * después de que obtuvo su JWT.
     */
    $staffMember->update([
        'active' => false,
    ]);

    $this
        ->withToken($token)
        ->getJson('/api/auth/me')
        ->assertForbidden()
        ->assertJson([
            'message' => 'Cuenta inhabilitada.',
        ]);
});
