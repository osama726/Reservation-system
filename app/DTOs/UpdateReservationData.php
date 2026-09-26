<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Fields are nullable because Update supports partial updates: the client
 * may send only `units`, or only the time range, or both.
 */
final readonly class UpdateReservationData
{
    public function __construct(
        public ?int $units,
        public ?CarbonImmutable $startTime,
        public ?CarbonImmutable $endTime,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            units: $request->has('units') ? (int) $request->input('units') : null,
            startTime: $request->has('start_time') ? CarbonImmutable::parse($request->input('start_time')) : null,
            endTime: $request->has('end_time') ? CarbonImmutable::parse($request->input('end_time')) : null,
        );
    }
}
