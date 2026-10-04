<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rota pública: o usuário ainda não tem token neste ponto.
Route::post('/login', [AuthController::class, 'login']);

// Todas as rotas abaixo exigem um token Sanctum válido.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('rooms', RoomController::class);

    Route::post('/reservations', [ReservationController::class, 'store']);

    // Pagamentos de uma reserva.
    Route::get('/reservations/{reservation}/payments', [PaymentController::class, 'index']);
    Route::post('/reservations/{reservation}/payments', [PaymentController::class, 'store']);
    Route::get('/reservations/{reservation}/payments/{payment}', [PaymentController::class, 'show']);
    Route::delete('/reservations/{reservation}/payments/{payment}', [PaymentController::class, 'destroy']);
});
