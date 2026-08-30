<?php

it('indica que la API está funcionando correctamente', function () {
    $response = $this->getJson('/api/estado');

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'API SIMRED funcionando correctamente',
            'database' => 'PostgreSQL',
            'framework' => 'Laravel 13',
        ]);
});
