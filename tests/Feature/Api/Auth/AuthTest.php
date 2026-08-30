<?php

use App\Models\User;

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
