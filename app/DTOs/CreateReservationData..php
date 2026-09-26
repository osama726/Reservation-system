<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final readonly class CreateReservationData
{
    public function __construct(
        public int $resourceId,
        public int $units,
        public CarbonImmutable $startTime,
        public CarbonImmutable $endTime,
        public ?string $id = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            resourceId: (int) $request->input('resource_id'),
            units: (int) $request->input('units'),
            startTime: CarbonImmutable::parse($request->input('start_time')),
            endTime: CarbonImmutable::parse($request->input('end_time')),
            id: $request->input('id'),
        );
    }
}
