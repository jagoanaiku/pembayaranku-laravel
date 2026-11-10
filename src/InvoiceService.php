<?php

declare(strict_types=1);

namespace PembayaranKu\Laravel;

use InvalidArgumentException;
use PembayaranKu\Client;

final class InvoiceService
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function create(array $input, string $idempotencyKey): array
    {
        return $this->client->createInvoice($input, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $invoiceId): array
    {
        return $this->client->getInvoice($invoiceId);
    }

    /** @param array{page?: int, limit?: int, status?: string} $options @return array<string, mixed> */
    public function list(array $options = []): array
    {
        return $this->client->listInvoices($options);
    }

    /** @return array<string, mixed> */
    public function cancel(string $invoiceId): array
    {
        return $this->client->cancelInvoice($invoiceId);
    }

    /** @param array<string, mixed> $invoice */
    public function paymentUrl(array $invoice): string
    {
        $url = (string) ($invoice['payment_url'] ?? '');
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || !in_array(strtolower((string) ($parts['host'] ?? '')), ['www.pembayaranku.com', 'pembayaranku.com'], true)
            || (isset($parts['port']) && $parts['port'] !== 443)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !preg_match('#^/pay/[0-9a-f]{64}$#', (string) ($parts['path'] ?? ''))) {
            throw new InvalidArgumentException('Invoice does not contain a trusted PembayaranKU Hosted Checkout URL.');
        }
        return $url;
    }
}
