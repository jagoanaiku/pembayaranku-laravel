<?php

declare(strict_types=1);

namespace App\Payments;

use Illuminate\Support\Facades\DB;
use PembayaranKu\Laravel\Contracts\WebhookHandler;

final class PembayaranKuWebhookHandler implements WebhookHandler
{
    public function handle(array $event, string $eventId, string $environment): void
    {
        if ($event['type'] === 'webhook.test') {
            return;
        }
        $data = $event['data'];
        $status = $event['type'] === 'payment.paid' ? 'paid' : ($event['type'] === 'payment.confirmation_changed'
            ? ($data['invoice_status'] ?? null)
            : ($data['status'] ?? null));
        if ($status !== 'paid') {
            // pending, matched, verified, failed, expired, and cancelled are not successful orders.
            return;
        }

        DB::table('orders')
            ->where('pembayaranku_invoice_id', $data['invoice_id'])
            ->where('payment_status', '!=', 'paid')
            ->update([
                'payment_status' => 'paid',
                'paid_at' => $data['paid_at'] ?? now(),
                'updated_at' => now(),
            ]);
    }
}
