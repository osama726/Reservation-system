<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            'resource_id' => $this->resource_id,
            'reservation_number' => $this->reservation_number,
            'units' => $this->units,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'status' => $this->status?->value ?? $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
        return $data;
    }
}
