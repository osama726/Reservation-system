<?php

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use Illuminate\Support\Facades\DB;


class ExpireReservationAction
{
    public function __invoke(string $reservationId): ?Reservation
    {
        return DB::transaction(function () use ($reservationId) {
            $reservation = Reservation::query()->lockForUpdate()->find($reservationId);

            if (! $reservation) {
                return null;
            }

            // Only expire reservations that are pending and have an expiration time in the past.
            if ($reservation->status !== ReservationStatus::Pending
                || $reservation->expires_at === null
                || $reservation->expires_at->isFuture()) {
                return $reservation;
            }

            $before = $reservation->toArray();

            $reservation->update([
                'status' => ReservationStatus::Expired,
                'version' => $reservation->version + 1,
            ]);

            ReservationHistory::record(
                reservation: $reservation,
                action: 'expired',
                oldData: $before,
                newData: $reservation->fresh()->toArray(),
            );

            return $reservation;
        });
    }
}
