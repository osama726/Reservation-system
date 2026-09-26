<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when Admin attempts to reduce a resource's capacity
 * below the minimum required to accommodate existing reservations at peak concurrency.
 */
class InvalidCapacityReductionException extends Exception
{
    public function __construct(
        public readonly int $requestedCapacity,
        public readonly int $minimumRequiredCapacity,
    ) {
        parent::__construct(
            "Cannot reduce capacity to {$requestedCapacity}: existing reservations "
            . "require at least {$minimumRequiredCapacity} at peak concurrency."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_capacity_reduction',
            'requested_capacity' => $this->requestedCapacity,
            'minimum_required_capacity' => $this->minimumRequiredCapacity,
        ], 409);
    }
}
