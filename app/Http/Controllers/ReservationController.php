<?php

namespace App\Http\Controllers;

use App\Actions\Reservation\CancelReservationAction;
use App\Actions\Reservation\ConfirmReservationAction;
use App\Actions\Reservation\CreateReservationAction;
use App\Actions\Reservation\UpdateReservationAction;
use App\DTOs\CreateReservationData;
use App\DTOs\UpdateReservationData;
use App\Http\Requests\CheckAvailabilityRequest;
use App\Http\Requests\CreateReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Resource;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function store(CreateReservationRequest $request, CreateReservationAction $action): ReservationResource
    {
        $reservation = $action(CreateReservationData::fromRequest($request));

        return ReservationResource::make($reservation);
    }

    public function confirm(string $reservation, ConfirmReservationAction $action): ReservationResource
    {
        $result = $action($reservation);

        return ReservationResource::make($result);
    }

    public function cancel(string $reservation, CancelReservationAction $action): ReservationResource
    {
        $result = $action($reservation);

        return ReservationResource::make($result);
    }

    public function update(UpdateReservationRequest $request, string $reservation, UpdateReservationAction $action): ReservationResource
    {
        $result = $action($reservation, UpdateReservationData::fromRequest($request));

        return ReservationResource::make($result);
    }

    /**
     * Check the availability of a resource for a given time range.
     */
    public function availability(
        CheckAvailabilityRequest $request,
        Resource $resource,
        AvailabilityService $availability,
    ): JsonResponse {
        $start = CarbonImmutable::parse($request->input('start_time'));
        $end = CarbonImmutable::parse($request->input('end_time'));

        $booked = $availability->getBookedUnits($resource->id, $start, $end);

        return response()->json([
            'resource_id'       => $resource->id,
            'capacity'          => $resource->capacity,
            'booked_units'      => $booked,
            'available_units'   => max(0, $resource->capacity - $booked),
            'start_time'        => $start->toIso8601String(),
            'end_time'          => $end->toIso8601String(),
        ]);
    }
}
