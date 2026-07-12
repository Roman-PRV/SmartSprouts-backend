<?php

use App\Games\FindTheWrong\Http\Controllers\Admin\FindTheWrongItemController;
use App\Http\Controllers\Admin\LevelController as AdminLevelController;
use App\Http\Controllers\Admin\StatementController as AdminStatementController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\LegalController;
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
Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
Route::middleware('auth:sanctum')->post('auth/logout', [AuthController::class, 'logout']);

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:auth-oauth-callback');

// ── Legal (public) ────────────────────────────────────────────────────────────

Route::get('legal/versions', [LegalController::class, 'versions'])->name('legal.versions');

// ── Player ────────────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile/password', [ProfilePasswordController::class, 'update'])->name('profile.password.update');
    Route::post('profile/consents', [ConsentController::class, 'store'])->name('profile.consents.store');

    Route::apiResource('games', GameController::class)->only(['index', 'show'])
        ->whereNumber('game');

    Route::apiResource('games.levels', LevelController::class)
        ->only(['index', 'show'])
        ->whereNumber(['game', 'level']);

    Route::post('games/{game}/levels/{level}/attempts', [AttemptController::class, 'store'])
        ->name('games.levels.attempts')
        ->whereNumber(['game', 'level']);

});

// ── Admin — levels (generic) ──────────────────────────────────────────────────

Route::middleware(['auth:sanctum', EnsureAdmin::class])
    ->prefix('admin/games/{game}')
    ->name('admin.games.levels.')
    ->whereNumber('game')
    ->group(function () {
        Route::get('levels', [AdminLevelController::class, 'index'])->name('index');
        Route::post('levels', [AdminLevelController::class, 'store'])->name('store');
        Route::get('levels/{level}', [AdminLevelController::class, 'show'])
            ->name('show')
            ->whereNumber('level');
        Route::match(['put', 'patch'], 'levels/{level}', [AdminLevelController::class, 'update'])
            ->name('update')
            ->whereNumber('level');
        Route::delete('levels/{level}', [AdminLevelController::class, 'destroy'])
            ->name('destroy')
            ->whereNumber('level');
        Route::post('levels/{level}/audio/regenerate', [AdminLevelController::class, 'regenerateAudio'])
            ->name('audio.regenerate')
            ->whereNumber('level');
    });

// ── Find the wrong — admin (game-specific items) ────────────────────────────────

Route::middleware([
    'auth:sanctum',
    EnsureAdmin::class,
    GameMatches::class.':find_the_wrong',
])
    ->prefix('admin/games/{game}')
    ->name('admin.games.find-the-wrong.')
    ->whereNumber('game')
    ->group(function () {
        Route::post('levels/{level}/items', [FindTheWrongItemController::class, 'store'])
            ->name('levels.items.store')
            ->whereNumber('level');
        Route::match(['put', 'patch'], 'items/{item}', [FindTheWrongItemController::class, 'update'])
            ->name('items.update')
            ->whereNumber('item');
        Route::delete('items/{item}', [FindTheWrongItemController::class, 'destroy'])
            ->name('items.destroy')
            ->whereNumber('item');
    });

// ── Admin — statements (generic, dispatched by game prefix) ─────────────────────
// {statement} lookups are scoped to the game-from-URL's table (the prefix selects
// the model), so a statement id from another game simply 404s. No GameMatches
// ownership middleware is needed here, unlike find-the-wrong's per-record check.

Route::middleware(['auth:sanctum', EnsureAdmin::class])
    ->prefix('admin/games/{game}')
    ->name('admin.games.statements.')
    ->whereNumber('game')
    ->group(function () {
        Route::post('levels/{level}/statements', [AdminStatementController::class, 'store'])
            ->name('store')
            ->whereNumber('level');
        Route::match(['put', 'patch'], 'statements/{statement}', [AdminStatementController::class, 'update'])
            ->name('update')
            ->whereNumber('statement');
        Route::delete('statements/{statement}', [AdminStatementController::class, 'destroy'])
            ->name('destroy')
            ->whereNumber('statement');
        Route::post('statements/{statement}/audio/regenerate', [AdminStatementController::class, 'regenerateAudio'])
            ->name('audio.regenerate')
            ->whereNumber('statement');
    });
