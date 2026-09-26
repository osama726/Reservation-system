<?php

namespace App\Actions\Reservation;

use App\DTOs\CreateReservationData;
use App\Enums\ReservationStatus;
use App\Models\ReservationHistory;
use App\Models\Resource;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateReservationAction
{
    public function __construct(
        private readonly AvailabilityService $availability,
    ) {
    }

    public function __invoke(CreateReservationData $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            $resource = Resource::query()->lockForUpdate()->findOrFail($data->resourceId);

            $this->availability->assertCapacityAvailable(
                resource: $resource,
                start: $data->startTime,
                end: $data->endTime,
                requestedUnits: $data->units,
            );

            $attributes = [
                'resource_id' => $resource->id,
                'units' => $data->units,
                'start_time' => $data->startTime,
                'end_time' => $data->endTime,
                'status' => ReservationStatus::Pending,
                'expires_at' => now()->addMinutes(30),
                'reservation_number' => random_int(100000, 99999999),
            ];

            if ($data->id !== null) {
                $attributes['id'] = $data->id;
            }

            $reservation = Reservation::create($attributes);

            ReservationHistory::record(
                reservation: $reservation,
                action: 'created',
                oldData: null,
                newData: $reservation->fresh()->toArray(),
            );

            return $reservation;
        });
    }
}
