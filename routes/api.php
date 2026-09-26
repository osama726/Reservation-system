<?php

use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reservation Service Routes
|--------------------------------------------------------------------------
| No auth required (per spec). Every write route goes through the
| `idempotent` middleware alias (see EnsureIdempotency + registration
| instructions in the README).
*/

Route::get('/resources/{resource}/availability', [ReservationController::class, 'availability']);

Route::middleware('idempotent')->group(function () {
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::post('/reservations/{reservation}/confirm', [ReservationController::class, 'confirm']);
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update']);

    Route::patch('/resources/{resource}/capacity', [ResourceController::class, 'updateCapacity']);
});
