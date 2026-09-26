<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a reservation request exceeds the available capacity of the resource.
 */
class CapacityExceededException extends Exception
{
    public function __construct(
        public readonly int $capacity,
        public readonly int $alreadyBooked,
        public readonly int $requestedUnits,
    ) {
        parent::__construct(
            "Requested {$requestedUnits} unit(s) but only "
            . max(0, $capacity - $alreadyBooked)
            . " of {$capacity} are available for the requested period."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'capacity_exceeded',
            'capacity' => $this->capacity,
            'already_booked' => $this->alreadyBooked,
            'requested_units' => $this->requestedUnits,
        ], 409);
    }
}
