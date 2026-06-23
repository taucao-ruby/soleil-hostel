<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Payment\MoMoPaymentController;
use App\Models\Booking;
use App\Models\MoMoPayment;
use App\Services\MoMoService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * DEV-ONLY tool: simulate a completed MoMo payment by firing a locally-signed IPN
 * at the booking, without any MoMo sandbox account or public tunnel.
 *
 * It mints the authoritative momo_payments order (what MoMoPaymentController::create
 * does in production), builds the server→server IPN payload exactly as the feature
 * test does, signs it with the REAL MoMoService::signIpn (so the controller's HMAC
 * verify passes by construction), then drives the genuine controller ipn() ladder
 * (F1–F6: verify → INSERT-first dedup → MoMoIpnHandler → booking confirm).
 *
 * It deliberately does NOT prove byte-compatibility with MoMo's real IPN field
 * order (IPN_SIGNATURE_FIELDS is still [UNPROVEN]); only a live sandbox callback can.
 * This exercises our own confirm/dedup/state path end-to-end and is safe to re-run.
 */
final class SimulateMoMoIpn extends Command
{
    protected $signature = 'momo:simulate-ipn
        {booking? : Booking ID (defaults to the latest pending prepaid booking)}
        {--result=0 : MoMo resultCode — 0 = success, non-zero = failed/cancelled (no confirm)}
        {--amount= : Override the notified amount (VND) to exercise the mismatch guard}
        {--replay : Fire the same IPN twice to demonstrate the idempotent dedup ack}';

    protected $description = 'Dev-only: fire a locally-signed MoMo IPN to simulate a completed wallet/bank payment.';

    public function handle(MoMoService $momo): int
    {
        if ($this->getLaravel()->isProduction()) {
            $this->error('Refusing to run in production — this confirms a booking without a real payment.');

            return self::FAILURE;
        }

        $booking = $this->resolveBooking();

        if ($booking === null) {
            return self::FAILURE;
        }

        // The IPN signature gate (F1/F3) fail-closes on a blank secret. In a fresh
        // dev env MoMo creds are null, so seed a sim secret/access key for THIS
        // process only — sign and verify both read it from config, staying symmetric.
        if ((string) config('services.momo.secret_key') === '') {
            config(['services.momo.secret_key' => 'local-sim-secret']);
            $this->warn('services.momo.secret_key was empty → using a throwaway sim secret for this run.');
        }

        if ((string) config('services.momo.access_key') === '') {
            config(['services.momo.access_key' => 'F8BBA842ECF85']);
        }

        $currency = strtolower((string) ($booking->payment_currency ?: 'vnd'));
        $orderId = $momo->orderId($booking);

        // Mint the authoritative order the IPN handler resolves through (booking +
        // pinned expected amount). This is what create() persists in production.
        MoMoPayment::firstOrCreate(
            ['order_id' => $orderId],
            [
                'booking_id' => $booking->id,
                'request_id' => (string) Str::uuid(),
                'expected_amount' => (int) $booking->amount,
                'currency' => $currency,
                'status' => 'pending',
            ],
        );

        $notifiedAmount = $this->option('amount') !== null
            ? (string) (int) $this->option('amount')
            : (string) (int) $booking->amount;

        $resultCode = (int) $this->option('result');

        $payload = [
            'partnerCode' => 'MOMO',
            'orderId' => $orderId,
            'requestId' => (string) Str::uuid(),
            'amount' => $notifiedAmount,
            'orderInfo' => 'Thanh toan dat phong Soleil #'.$booking->id,
            'orderType' => 'momo_wallet',
            'transId' => (string) random_int(10000000, 99999999),
            'resultCode' => $resultCode,
            'message' => $resultCode === 0 ? 'Successful.' : 'Simulated failure.',
            'payType' => 'qr',
            'responseTime' => (string) now()->valueOf(),
            'extraData' => '',
        ];
        $payload['signature'] = $momo->signIpn($payload);

        $statusBefore = $booking->status->value;
        $paymentBefore = $booking->payment_status->value;

        $this->newLine();
        $this->line("<info>Booking</info>      #{$booking->id}  (status={$statusBefore}, payment={$paymentBefore}, amount=".(int) $booking->amount.' '.$currency.')');
        $this->line("<info>Order</info>        {$orderId}");
        $this->line('<info>IPN</info>          amount='.$notifiedAmount."  resultCode={$resultCode}  signature ".($momo->verifyIpnSignature($payload) ? '<info>verifies ✓</info>' : '<error>FAILS ✗</error>'));
        $this->newLine();

        $first = $this->fireIpn($payload);
        $this->line("→ IPN #1 HTTP {$this->describeStatus($first)}");

        if ($this->option('replay')) {
            $second = $this->fireIpn($payload);
            $this->line("→ IPN #2 (replay) HTTP {$this->describeStatus($second)}  ".($second === 204 ? '<info>deduped, no double confirm</info>' : ''));
        }

        $booking->refresh();
        $this->newLine();
        $this->line('<info>Result</info>       status='.$booking->status->value.'  payment='.$booking->payment_status->value);

        $confirmed = $booking->status->value === 'confirmed';
        $this->line($confirmed
            ? '<info>✓ Booking confirmed — payment simulated successfully.</info>'
            : '<comment>Booking not confirmed (check resultCode / amount / current status above).</comment>');

        return self::SUCCESS;
    }

    private function resolveBooking(): ?Booking
    {
        $arg = $this->argument('booking');

        if ($arg !== null) {
            $booking = Booking::find((int) $arg);

            if ($booking === null) {
                $this->error("Booking #{$arg} not found.");
                $this->showPendingCandidates();
            }

            return $booking;
        }

        $booking = Booking::query()
            ->where('status', 'pending')
            ->where('payment_policy', 'prepaid')
            ->latest('id')
            ->first();

        if ($booking === null) {
            $this->error('No pending prepaid booking found to simulate against. Create one at /booking first.');

            return null;
        }

        $this->info("No booking given — using latest pending prepaid booking #{$booking->id}.");

        return $booking;
    }

    private function showPendingCandidates(): void
    {
        $candidates = Booking::query()
            ->where('status', 'pending')
            ->where('payment_policy', 'prepaid')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'amount']);

        if ($candidates->isEmpty()) {
            return;
        }

        $this->line('Pending prepaid bookings you can target:');
        foreach ($candidates as $candidate) {
            $this->line("  #{$candidate->id}  amount=".(int) $candidate->amount);
        }
    }

    private function fireIpn(array $payload): int
    {
        $request = Request::create(
            '/api/v1/payments/momo/ipn',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
        );

        return app(MoMoPaymentController::class)->ipn($request)->getStatusCode();
    }

    private function describeStatus(int $status): string
    {
        return match ($status) {
            204 => '204 (ack)',
            400 => '400 (rejected: bad signature / malformed)',
            500 => '500 (misconfig / handler error)',
            default => (string) $status,
        };
    }
}
