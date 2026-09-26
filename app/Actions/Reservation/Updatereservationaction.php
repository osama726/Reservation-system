<?php

namespace App\Actions\Reservation;

use App\DTOs\UpdateReservationData;
use App\Enums\ReservationStatus;
use App\Exceptions\InvalidReservationStateException;
use App\Exceptions\ReservationExpiredException;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use App\Models\Resource;
use App\Services\AvailabilityService;
use Illuminate\Support\Facades\DB;

class UpdateReservationAction
{
    public function __construct(
        private readonly AvailabilityService $availability,
    ) {
    }

    public function __invoke(string $reservationId, UpdateReservationData $data): Reservation
    {
        return DB::transaction(function () use ($reservationId, $data) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);
            $before = $reservation->toArray();

            if ($reservation->status !== ReservationStatus::Pending
                && $reservation->status !== ReservationStatus::Confirmed) {
                throw new InvalidReservationStateException($reservation->status, 'update');
            }

            if ($reservation->status === ReservationStatus::Pending
                && $reservation->expires_at !== null
                && $reservation->expires_at->isPast()) {
                throw new ReservationExpiredException($reservationId);
            }

            // Lock the parent resource too: units/time are changing, so we
            // must re-check capacity against every OTHER reservation, and
            // that comparison has to be serialized the same way Create is.
            $resource = Resource::query()->lockForUpdate()->findOrFail($reservation->resource_id);

            $newUnits = $data->units ?? $reservation->units;
            $newStart = $data->startTime ?? $reservation->start_time;
            $newEnd = $data->endTime ?? $reservation->end_time;

            $this->availability->assertCapacityAvailable(
                resource: $resource,
                start: $newStart,
                end: $newEnd,
                requestedUnits: $newUnits,
                excludeReservationId: $reservation->id,
            );

            $reservation->update([
                'units' => $newUnits,
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'version' => $reservation->version + 1,
            ]);

            ReservationHistory::record(
                reservation: $reservation,
                action: 'updated',
                oldData: $before,
                newData: $reservation->fresh()->toArray(),
            );

            return $reservation;
        });
    }
}
