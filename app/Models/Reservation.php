<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'resource_id',
        'reservation_number',
        'units',
        'start_time',
        'end_time',
        'status',
        'expires_at',
        'version',
    ];

    protected $casts = [
        'units' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'status' => ReservationStatus::class,
        'expires_at' => 'datetime',
        'version' => 'integer',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ReservationHistory::class);
    }

    /**
     * A reservation currently "occupies" capacity if it's confirmed,
     * or pending and not yet expired. This mirrors the condition used
     * by AvailabilityService's overlap query.
     */
    public function isActivelyOccupying(): bool
    {
        return $this->status === ReservationStatus::Confirmed
            || ($this->status === ReservationStatus::Pending
                && $this->expires_at !== null
                && $this->expires_at->isFuture());
    }
}
