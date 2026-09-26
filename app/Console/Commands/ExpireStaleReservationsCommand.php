<?php

namespace App\Console\Commands;

use App\Actions\Reservation\ExpireReservationAction;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Console\Command;

/**
 * Cleanup only. Availability is already computed correctly without this
 * command ever running (see AvailabilityService's lazy expires_at check).
 * This just flips stale rows to `expired` in the DB and writes the history
 * entry so the data is tidy and auditable even for reservations nobody
 * looks at again.
 */
class ExpireStaleReservationsCommand extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Mark pending reservations past their expiry as expired';

    public function handle(ExpireReservationAction $action): int
    {
        $staleIds = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->where('expires_at', '<=', now())
            ->pluck('id');

        foreach ($staleIds as $id) {
            $action($id);
        }

        $this->info(count($staleIds) . ' reservation(s) expired.');

        return self::SUCCESS;
    }
}
