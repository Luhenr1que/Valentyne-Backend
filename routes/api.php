<?php

use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\ButtonController;
use App\Http\Controllers\DrawController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MemoryController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\SadCardController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushTokenController;
use App\Http\Controllers\TravelController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/health',      HealthController::class);
Route::get('/app-version', [AppVersionController::class, 'index']);
Route::post('/app-version',[AppVersionController::class, 'store']);

Route::prefix('tickets')->group(function () {
    Route::get('/',         [TicketController::class, 'index']);
    Route::get('/types',    [TicketController::class, 'types']);
    Route::post('/draw',    [TicketController::class, 'draw']);
    Route::patch('/{id}/status', [TicketController::class, 'updateStatus']);
});

Route::get('/music',    [MusicController::class,   'index']);
Route::get('/draws',    [DrawController::class,    'index']);
Route::get('/memories', [MemoryController::class,  'index']);
Route::get('/buttons',  [ButtonController::class,  'index']);
Route::get('/sad-cards',[SadCardController::class, 'index']);
Route::get('/travel',   [TravelController::class,  'index']);

Route::post('/push-token',    [PushTokenController::class,  'store']);
Route::delete('/push-token',  [PushTokenController::class,  'destroy']);
Route::post('/notify',        [NotificationController::class, 'send']);

Route::prefix('birthday')->group(function () {
    Route::get('/',         [BirthdayController::class, 'index']);
    Route::post('/unlock',  [BirthdayController::class, 'unlock']);
});

Route::prefix('wishlist')->group(function () {
    Route::get('/',        [WishlistController::class, 'index']);
    Route::post('/',       [WishlistController::class, 'store']);
    Route::delete('/{id}', [WishlistController::class, 'destroy']);
});
