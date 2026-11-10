<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use PembayaranKu\Laravel\HostedCheckout;
use PembayaranKu\Laravel\InvoiceService;

final class CheckoutController
{
    public function __invoke(Order $order, InvoiceService $payments, HostedCheckout $checkout): RedirectResponse
    {
        // Persist one idempotency key per order before calling the API and reuse it on retries.
        $idempotencyKey = 'order-' . $order->public_id;
        $invoice = $payments->create([
            'amount' => (int) $order->total_idr,
            'description' => 'Order ' . $order->number,
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
            ],
        ], $idempotencyKey);

        return $checkout->redirect($invoice);
    }
}
