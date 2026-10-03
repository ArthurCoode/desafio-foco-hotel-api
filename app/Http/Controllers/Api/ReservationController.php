<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Services\Reservation\CreateReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReservationController extends Controller
{
    public function store(
        StoreReservationRequest $request,
        CreateReservationService $createReservation,
    ): JsonResponse {
        $reservation = $createReservation->create($request->validated());

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
