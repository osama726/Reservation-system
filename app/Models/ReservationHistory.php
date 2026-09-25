<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationHistory extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // history rows are append-only, never updated

    protected $fillable = [
        'reservation_id',
        'action',
        'old_data',
        'new_data',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Convenience helper used by every Action so the "record a history row"
     * call reads the same everywhere and always happens inside the same
     * DB transaction as the state change it is describing.
     */
    public static function record(Reservation $reservation, string $action, ?array $oldData, array $newData): self
    {
        return self::create([
            'reservation_id' => $reservation->id,
            'action' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }
}
