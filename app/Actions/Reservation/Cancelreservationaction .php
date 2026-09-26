<?php

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Exceptions\InvalidReservationStateException;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    public function __invoke(string $reservationId): Reservation
    {
        return DB::transaction(function () use ($reservationId) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);
            $before = $reservation->toArray();

            // Cancelling only makes sense from pending or confirmed.
            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new InvalidReservationStateException($reservation->status, 'cancel');
            }

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'expires_at' => null,
                'version' => $reservation->version + 1,
            ]);

            ReservationHistory::record(
                reservation: $reservation,
                action: 'cancelled',
                oldData: $before,
                newData: $reservation->fresh()->toArray(),
            );

            return $reservation;
        });
    }
}
