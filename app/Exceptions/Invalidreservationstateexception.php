<?php

namespace App\Exceptions;

use App\Enums\ReservationStatus;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when an operation is attempted on a reservation whose current
 * status doesn't allow it (e.g. confirming an already-cancelled reservation,
 * or updating one that already expired).
 */
class InvalidReservationStateException extends Exception
{
    public function __construct(
        public readonly ReservationStatus $currentStatus,
        string $operation,
    ) {
        parent::__construct(
            "Cannot {$operation} a reservation with status '{$currentStatus->value}'."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_reservation_state',
            'current_status' => $this->currentStatus->value,
        ], 409);
    }
}
