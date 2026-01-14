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

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('auth/logout', [AuthController::class, 'logout']);

Route::apiResource('games', GameController::class)->only(['index', 'show']);

Route::apiResource('games.levels', LevelController::class)
    ->only(['index', 'show']);
Route::post('games/{game}/levels/{levelId}/check', [LevelController::class, 'check'])
    ->name('games.levels.check');

// Temporary debug route for localization verification
Route::get('/debug/locale', function () {
    return response()->json([
        'locale' => app()->getLocale(),
        'supported' => config('app.supported_locales'),
        'fallback' => config('app.fallback_locale'),
    ]);
});
