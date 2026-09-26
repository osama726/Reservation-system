<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class ReservationResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     */
    public $attributes = [
        'reservation_number',
        'units',
        'start_time',
        'end_time',
        'status',
        'expires_at',
        'version',
        'created_at',
        'updated_at',
    ];

    /**
     * The resource's relationships.
     */
    public $relationships = [
        'resource',
        'history',
    ];
}
