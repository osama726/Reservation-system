<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Exceptions\CapacityExceededException;
use App\Models\Reservation;
use App\Models\Resource;
use Carbon\CarbonInterface;

class AvailabilityService
{

    /**
     * Returns the number of units booked for a given resource and time window.
     * Optionally excludes a specific reservation from the count (useful for updates).
     */
    public function getBookedUnits(
        int $resourceId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $excludeReservationId = null,
    ): int {
        return Reservation::query()
            ->where('resource_id', $resourceId)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->where(function ($query) {
                $query->where('status', ReservationStatus::Confirmed->value)
                    ->orWhere(function ($pending) {
                        $pending->where('status', ReservationStatus::Pending->value)
                            ->where('expires_at', '>', now());
                    });
            })
            ->when(
                $excludeReservationId,
                fn ($query) => $query->where('id', '!=', $excludeReservationId)
            )
            ->sum('units');
    }

    /**
     * Asserts that the requested number of units is available for the given resource and time window.
     * @throws CapacityExceededException
     */
    public function assertCapacityAvailable(
        Resource $resource,
        CarbonInterface $start,
        CarbonInterface $end,
        int $requestedUnits,
        ?string $excludeReservationId = null,
    ): void {
        $booked = $this->getBookedUnits($resource->id, $start, $end, $excludeReservationId);

        if ($booked + $requestedUnits > $resource->capacity) {
            throw new CapacityExceededException($resource->capacity, $booked, $requestedUnits);
        }
    }

    /**
     * Returns the maximum number of units concurrently booked for a given resource
     * from a given point in time (or now if not specified) into the future.
     */
    public function maxConcurrentUnits(int $resourceId, ?CarbonInterface $from = null): int
    {
        $from ??= now();

        $reservations = Reservation::query()
            ->where('resource_id', $resourceId)
            ->where('end_time', '>', $from)
            ->where(function ($query) {
                $query->where('status', ReservationStatus::Confirmed->value)
                    ->orWhere(function ($pending) {
                        $pending->where('status', ReservationStatus::Pending->value)
                            ->where('expires_at', '>', now());
                    });
            })
            ->get(['start_time', 'end_time', 'units']);

        $events = [];

        foreach ($reservations as $reservation) {
            $start = $reservation->start_time->max($from);
            $events[] = [$start->getTimestamp(), $reservation->units];
            $events[] = [$reservation->end_time->getTimestamp(), -$reservation->units];
        }

        usort($events, function (array $a, array $b) {
            return $a[0] <=> $b[0] ?: $a[1] <=> $b[1]; // ends (negative) before starts (positive)
        });

        $current = 0;
        $peak = 0;

        foreach ($events as [, $delta]) {
            $current += $delta;
            $peak = max($peak, $current);
        }

        return $peak;
    }
}
