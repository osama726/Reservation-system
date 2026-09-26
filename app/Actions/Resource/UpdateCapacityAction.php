<?php

namespace App\Actions\Resource;

use App\Exceptions\InvalidCapacityReductionException;
use App\Models\Resource;
use App\Services\AvailabilityService;
use Illuminate\Support\Facades\DB;

class UpdateCapacityAction
{
    public function __construct(
        private readonly AvailabilityService $availability,
    ) {
    }

    public function __invoke(int $resourceId, int $newCapacity): Resource
    {
        return DB::transaction(function () use ($resourceId, $newCapacity) {
            $resource = Resource::query()->lockForUpdate()->findOrFail($resourceId);

            if ($newCapacity < $resource->capacity) {
                $peak = $this->availability->maxConcurrentUnits($resource->id);

                if ($newCapacity < $peak) {
                    throw new InvalidCapacityReductionException($newCapacity, $peak);
                }
            }

            $resource->update(['capacity' => $newCapacity]);

            return $resource;
        });
    }
}
