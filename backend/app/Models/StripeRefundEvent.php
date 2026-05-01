<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StripeRefundEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stripe_refund_id',
        'stripe_event_id',
        'booking_id',
        'amount_refunded',
        'currency',
    ];

    protected $casts = [
        'booking_id' => 'integer',
        'amount_refunded' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
