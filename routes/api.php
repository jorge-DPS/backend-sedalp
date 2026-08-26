<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Admin\Communication\NewsController;
use App\Http\Controllers\Api\Admin\Communication\NewsImageController;
use App\Http\Controllers\Api\Admin\Communication\NewsVideoController;

use App\Http\Controllers\Api\Admin\AccessControl\AccessCatalogController;
use App\Http\Controllers\Api\Admin\AccessControl\UserController;
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');


Route::get('/estado', function () {
  return response()->json([
    'success' => true,
    'message' => 'API SIMRED funcionando correctamente',
    'database' => 'PostgreSQL',
    'framework' => 'Laravel 13',
  ]);
});
Route::prefix('auth')->group(function () {

  Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

  Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
  });
});

Route::prefix('admin')->middleware('auth:api')->scopeBindings()->group(function () {

  // NOTICIAS

  Route::get('/news', [NewsController::class, 'index',])->middleware('can:news.view');

  Route::post('/news', [
    NewsController::class,
    'store',
  ])->middleware('can:news.create');

  Route::get('/news/{news}', [
    NewsController::class,
    'show',
  ])->middleware('can:news.view');

  Route::put('/news/{news}', [
    NewsController::class,
    'update',
  ])->middleware('can:news.update');

  Route::patch('/news/{news}', [
    NewsController::class,
    'update',
  ])->middleware('can:news.update');

  Route::delete('/news/{news}', [
    NewsController::class,
    'destroy',
  ])->middleware('can:news.delete');


  // IMÁGENES

  Route::post('/news/{news}/images', [
    NewsImageController::class,
    'store',
  ])->middleware('can:news.update');

  Route::patch('/news/{news}/images/{image}', [
    NewsImageController::class,
    'update',
  ])->middleware('can:news.update');

  Route::put('/news/{news}/images/reorder', [
    NewsImageController::class,
    'reorder',
  ])->middleware('can:news.update');

  Route::delete('/news/{news}/images/{image}', [
    NewsImageController::class,
    'destroy',
  ])->middleware('can:news.update');


  // VIDEOS

  Route::post('/news/{news}/videos', [
    NewsVideoController::class,
    'store',
  ])->middleware('can:news.update');

  Route::patch('/news/{news}/videos/{video}', [
    NewsVideoController::class,
    'update',
  ])->middleware('can:news.update');

  Route::put('/news/{news}/videos/reorder', [
    NewsVideoController::class,
    'reorder',
  ])->middleware('can:news.update');

  Route::delete('/news/{news}/videos/{video}', [
    NewsVideoController::class,
    'destroy',
  ])->middleware('can:news.update');

  /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

  Route::get('/users', [UserController::class, 'index'])
    ->middleware('can:users.view');

  Route::post('/users', [UserController::class, 'store'])
    ->middleware('can:users.create');

  Route::get('/users/{user}', [UserController::class, 'show'])
    ->middleware('can:users.view');

  Route::patch('/users/{user}', [UserController::class, 'update'])
    ->middleware('can:users.update');

  Route::put(
    '/users/{user}/access',
    [UserController::class, 'updateAccess']
  )->middleware('can:users.update');

  Route::delete('/users/{user}', [UserController::class, 'destroy'])
    ->middleware('can:users.delete');

  /*
  |--------------------------------------------------------------------------
  | Access control catalogs
  |--------------------------------------------------------------------------
  */

  Route::get(
    '/access/roles',
    [AccessCatalogController::class, 'roles']
  )->middleware('can:roles.view');

  Route::get(
    '/access/permissions',
    [AccessCatalogController::class, 'permissions']
  )->middleware('can:permissions.view');
});
