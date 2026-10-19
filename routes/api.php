<?php

use App\Http\Controllers\Api\GameApiController;
use App\Http\Controllers\Api\PvpApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Giao tiếp bất đồng bộ Fetch API / AJAX JSON (Chương 2 & Bài 9)
|--------------------------------------------------------------------------
*/

Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/leaderboard', [GameApiController::class, 'leaderboard'])->name('api.leaderboard');
    Route::get('/levels/search', [GameApiController::class, 'searchLevels'])->name('api.levels.search');

    Route::post('/auth/login', [GameApiController::class, 'login'])->name('api.auth.login');
    Route::post('/auth/register', [GameApiController::class, 'register'])->name('api.auth.register');
    Route::post('/auth/forgot-password', [GameApiController::class, 'forgotPassword'])->name('api.auth.forgot');
    Route::post('/auth/logout', [GameApiController::class, 'logout'])->name('api.auth.logout');

    Route::post('/game/save-session', [GameApiController::class, 'saveSession'])->name('api.game.save_session');
    Route::post('/game/reset-score', [GameApiController::class, 'resetScore'])->name('api.game.reset_score');

    // ==================== CÁC TUYẾN PVP REAL-TIME & MATCHMAKING ====================
    Route::post('/pvp/create-room', [PvpApiController::class, 'createRoom'])->name('api.pvp.create_room');
    Route::post('/pvp/join-room', [PvpApiController::class, 'joinRoom'])->name('api.pvp.join_room');
    Route::post('/pvp/leave-room', [PvpApiController::class, 'leaveRoom'])->name('api.pvp.leave_room');
    Route::post('/pvp/toggle-ready', [PvpApiController::class, 'toggleReady'])->name('api.pvp.toggle_ready');
    Route::post('/pvp/update-config', [PvpApiController::class, 'updateConfig'])->name('api.pvp.update_config');
    Route::post('/pvp/start-match', [PvpApiController::class, 'startMatch'])->name('api.pvp.start_match');
    Route::get('/pvp/room-status', [PvpApiController::class, 'getRoomStatus'])->name('api.pvp.room_status');
    Route::get('/pvp/match-status', [PvpApiController::class, 'getMatchStatus'])->name('api.pvp.match_status');
    Route::get('/pvp/match-results', [PvpApiController::class, 'getMatchResults'])->name('api.pvp.match_results');
    Route::post('/pvp/submit-move', [PvpApiController::class, 'submitMove'])->name('api.pvp.submit_move');
    Route::post('/pvp/submit-solution', [PvpApiController::class, 'submitSolution'])->name('api.pvp.submit_solution');
    Route::get('/pvp/reconnect', [PvpApiController::class, 'reconnect'])->name('api.pvp.reconnect');
    Route::post('/pvp/matchmaking/join', [PvpApiController::class, 'joinMatchmaking'])->name('api.pvp.matchmaking.join');
    Route::post('/pvp/matchmaking/cancel', [PvpApiController::class, 'cancelMatchmaking'])->name('api.pvp.matchmaking.cancel');
    Route::get('/pvp/leaderboard', [PvpApiController::class, 'getRankedLeaderboard'])->name('api.pvp.leaderboard');
    Route::post('/pvp/broadcasting/auth', [PvpApiController::class, 'broadcastingAuth'])->name('api.pvp.broadcasting.auth');
});
