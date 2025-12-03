<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LevelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::apiResource('games', GameController::class)->only(['index', 'show']);

Route::get('games/{game}/levels', [LevelController::class, 'index'])
    ->name('games.levels.index');
Route::get('games/{game}/levels/{levelId}', [LevelController::class, 'show'])
    ->name('games.levels.show');
Route::post('games/{game}/levels/{levelId}/check', [LevelController::class, 'check'])
    ->name('games.levels.check');
