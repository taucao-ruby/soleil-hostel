<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Authoritative record of a MoMo order this application minted at create() time
 * (Finding 3 / hardening).
 *
 * The IPN handler resolves BOTH the target booking and the pinned expected_amount
 * through this row, so a server→server notification can only ever confirm an order
 * we actually started — and against the amount quoted at order time, immune to a
 * later booking.amount change. Loose-coupled to bookings (no FK) to match the
 * momo_webhook_events pattern.
 */
final class MoMoPayment extends Model
{
    use HasFactory;

    /**
     * Str::snake('MoMoPayment') === 'mo_mo_payment'; pin the real table name so
     * model + migration agree (same reason as MoMoWebhookEvent).
     */
    protected $table = 'momo_payments';

    protected $fillable = [
        'booking_id',
        'order_id',
        'request_id',
        'expected_amount',
        'currency',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'booking_id' => 'integer',
        'expected_amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function markPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => $this->paid_at ?? now(),
        ]);
    }
}
