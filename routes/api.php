<?php

use App\Http\Controllers\Api\SkyCryptProxyController;
use App\Http\Controllers\KarmaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| SkyCrypt Profile Proxy
|--------------------------------------------------------------------------
*/
Route::get('/skycrypt/{username}', [SkyCryptProxyController::class, 'profile'])
    ->middleware('throttle:30,1')
    ->where('username', '[A-Za-z0-9_]{1,16}')
    ->name('api.skycrypt.profile');

/*
|--------------------------------------------------------------------------
| Karma Voting
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/karma/vote', [KarmaController::class, 'vote'])->name('api.karma.vote');
    Route::get('/karma/{targetId}', [KarmaController::class, 'status'])->name('api.karma.status');
});
