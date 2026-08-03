<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/estado', function () {
    return response()->json([
        'success' => true,
        'message' => 'API SIMRED funcionando correctamente',
        'database' => 'PostgreSQL',
        'framework' => 'Laravel 13',
    ]);
});
