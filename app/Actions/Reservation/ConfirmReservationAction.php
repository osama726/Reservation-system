<?php

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Exceptions\InvalidReservationStateException;
use App\Exceptions\ReservationExpiredException;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use Illuminate\Support\Facades\DB;

class ConfirmReservationAction
{
    public function __invoke(string $reservationId): Reservation
    {
        return DB::transaction(function () use ($reservationId) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);
            $before = $reservation->toArray();

            if ($reservation->status !== ReservationStatus::Pending) {
                throw new InvalidReservationStateException($reservation->status, 'confirm');
            }

            // If the reservation is pending but has expired, we cannot confirm it.
            if ($reservation->expires_at !== null && $reservation->expires_at->isPast()) {
                throw new ReservationExpiredException($reservationId);
            }

            $reservation->update([
                'status' => ReservationStatus::Confirmed,
                'expires_at' => null,
                'version' => $reservation->version + 1,
            ]);

            ReservationHistory::record(
                reservation: $reservation,
                action: 'confirmed',
                oldData: $before,
                newData: $reservation->fresh()->toArray(),
            );

            return $reservation;
        });
    }
}
