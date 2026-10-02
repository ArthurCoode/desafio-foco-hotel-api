<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = [
        'external_id',
        'hotel_id',
        'room_id',
        'check_in',
        'check_out',
        'status',
        'subtotal',
        'discount',
        'fees',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'check_in' => 'date',
            'check_out' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'fees' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function dailies(): HasMany
    {
        return $this->hasMany(ReservationDaily::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(
            Coupon::class,
            'reservation_coupons',
            'reservation_id',
            'coupon_id'
        )
            ->withPivot('discount')
            ->withTimestamps();
    }
}
