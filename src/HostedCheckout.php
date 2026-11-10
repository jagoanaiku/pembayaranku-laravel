<?php

declare(strict_types=1);

namespace PembayaranKu\Laravel;

use Illuminate\Http\RedirectResponse;

final class HostedCheckout
{
    /** @param array<string, mixed> $invoice */
    public function redirect(array $invoice): RedirectResponse
    {
        $url = app(InvoiceService::class)->paymentUrl($invoice);
        return redirect()->away($url);
    }
}
