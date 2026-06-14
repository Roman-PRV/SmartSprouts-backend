<?php

use App\Games\FindTheWrong\Http\Controllers\Admin\FindTheWrongItemController;
use App\Games\FindTheWrong\Http\Controllers\Admin\FindTheWrongLevelController;
use App\Games\FindTheWrong\Http\Controllers\FindTheWrongAttemptController;
use App\Http\Controllers\Admin\LevelController as AdminLevelController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePasswordController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\GameMatches;
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

// ── Auth ─────────────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->get('auth/me', [AuthController::class, 'me']);
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('auth/logout', [AuthController::class, 'logout']);

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);

// ── Player ────────────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile/password', [ProfilePasswordController::class, 'update'])->name('profile.password.update');

    Route::apiResource('games', GameController::class)->only(['index', 'show'])
        ->whereNumber('game');

    Route::apiResource('games.levels', LevelController::class)
        ->only(['index', 'show'])
        ->whereNumber(['game', 'level']);

    Route::post('games/{game}/levels/{level}/check', [LevelController::class, 'check'])
        ->name('games.levels.check')
        ->whereNumber(['game', 'level']);

});

// ── Find the wrong — player ───────────────────────────────────────────────────

Route::middleware([
    'auth:sanctum',
    GameMatches::class.':find_the_wrong',
])
    ->prefix('games/{game}')
    ->name('games.find-the-wrong.')
    ->whereNumber('game')
    ->group(function () {
        Route::post('levels/{level}/attempts', [FindTheWrongAttemptController::class, 'store'])
            ->name('levels.attempts.store')
            ->whereNumber('level');
    });

// ── Admin — levels (generic) ──────────────────────────────────────────────────

Route::middleware(['auth:sanctum', EnsureAdmin::class])
    ->prefix('admin/games/{game}')
    ->name('admin.games.levels.')
    ->whereNumber('game')
    ->group(function () {
        Route::get('levels', [AdminLevelController::class, 'index'])->name('index');
        Route::post('levels', [AdminLevelController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], 'levels/{level}', [AdminLevelController::class, 'update'])
            ->name('update')
            ->whereNumber('level');
        Route::delete('levels/{level}', [AdminLevelController::class, 'destroy'])
            ->name('destroy')
            ->whereNumber('level');
    });

// ── Find the wrong — admin ────────────────────────────────────────────────────

Route::middleware([
    'auth:sanctum',
    EnsureAdmin::class,
    GameMatches::class.':find_the_wrong',
])
    ->prefix('admin/games/{game}')
    ->name('admin.games.find-the-wrong.items.')
    ->whereNumber('game')
    ->group(function () {
        Route::get('levels/{level}', [FindTheWrongLevelController::class, 'show'])
            ->name('show')
            ->whereNumber('level');
        Route::post('levels/{level}/items', [FindTheWrongItemController::class, 'store'])
            ->name('store')
            ->whereNumber('level');
        Route::match(['put', 'patch'], 'items/{item}', [FindTheWrongItemController::class, 'update'])
            ->name('update')
            ->whereNumber('item');
        Route::delete('items/{item}', [FindTheWrongItemController::class, 'destroy'])
            ->name('destroy')
            ->whereNumber('item');
    });
