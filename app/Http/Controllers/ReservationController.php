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
use App\Models\Reservation;
use App\Models\Resource;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function store(CreateReservationRequest $request, CreateReservationAction $action): JsonResponse
    {
        $reservation = $action(CreateReservationData::fromRequest($request));

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }

    public function confirm(string $reservation, ConfirmReservationAction $action): JsonResponse
    {
        $result = $action($reservation);

        return (new ReservationResource($result))->response();
    }

    public function cancel(string $reservation, CancelReservationAction $action): JsonResponse
    {
        $result = $action($reservation);

        return (new ReservationResource($result))->response();
    }

    public function update(UpdateReservationRequest $request, string $reservation, UpdateReservationAction $action): JsonResponse
    {
        $result = $action($reservation, UpdateReservationData::fromRequest($request));

        return (new ReservationResource($result))->response();
    }

    public function availability(
        CheckAvailabilityRequest $request,
        Resource $resource,
        AvailabilityService $availability,
    ): JsonResponse {
        $start = CarbonImmutable::parse($request->input('start_time'));
        $end = CarbonImmutable::parse($request->input('end_time'));

        $booked = $availability->getBookedUnits($resource->id, $start, $end);

        return response()->json([
            'resource_id' => $resource->id,
            'capacity' => $resource->capacity,
            'booked_units' => $booked,
            'available_units' => max(0, $resource->capacity - $booked),
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
        ]);
    }

    public function history(string $reservation): JsonResponse
    {
        $reservation = Reservation::query()->with('history')->findOrFail($reservation);

        return response()->json([
            'data' => $reservation->history
                ->sortBy('created_at')
                ->values()
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'reservation_id' => $entry->reservation_id,
                    'action' => $entry->action,
                    'old_data' => $entry->old_data,
                    'new_data' => $entry->new_data,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }
}
