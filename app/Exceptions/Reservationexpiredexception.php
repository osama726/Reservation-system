<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationExpiredException extends Exception
{
    public function __construct(public readonly string $reservationId)
    {
        parent::__construct("Reservation {$reservationId} has expired and can no longer be confirmed or updated.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'reservation_expired',
            'reservation_id' => $this->reservationId,
        ], 410);
    }
}
