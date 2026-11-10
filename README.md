# PembayaranKU Laravel Integration

Official Laravel service provider, Merchant API client integration, Hosted Checkout helper, and signed webhook receiver. Release **1.0.2** targets PHP 8.1+ and Laravel 10, 11, and 12. It depends on [`pembayaranku/php-sdk` 1.1+](https://packagist.org/packages/pembayaranku/php-sdk).

This package's dedicated source repository is [jagoanaiku/pembayaranku-laravel](https://github.com/jagoanaiku/pembayaranku-laravel). The package name is `pembayaranku/laravel`. Its first Packagist registration is a separate owner action; once registered, install with:

```sh
composer require pembayaranku/laravel:^1.0.2
php artisan vendor:publish --tag=pembayaranku-config
php artisan migrate
```

Laravel package auto-discovery registers `PembayaranKuServiceProvider`. The migration creates `pembayaranku_webhook_events` for webhook event deduplication. No provider registration is needed manually.

## Configuration

Copy the appropriate entries from [`.env.example`](.env.example) to your application `.env` and run `php artisan config:clear` after changing them. Use a `pk_test_` key with `PEMBAYARANKU_ENVIRONMENT=sandbox` for Sandbox; use a `pk_live_` key with `PEMBAYARANKU_ENVIRONMENT=live` for Live. The PHP SDK checks the key prefix against the configured environment. Keep API keys and webhook secrets server-side; never expose them to browser JavaScript.

`PEMBAYARANKU_BASE_URL` defaults to the Merchant API V1 root. Leave it unchanged for Production. Fee values are returned from the immutable invoice snapshot; this package does not recalculate fees. Live invoice lifecycle is `pending → matched → paid`; Finance runs asynchronously after `paid` and must not delay merchant order fulfillment. Sandbox outcomes are simulations, not real QRIS payments.

## Invoice and Hosted Checkout

The package exposes `PembayaranKu\Laravel\InvoiceService` and the optional `PembayaranKu\Laravel\Facades\PembayaranKu` facade:

- `create(array $request, string $idempotencyKey)` creates an invoice through Merchant API V1.
- `retrieve(string $invoiceId)` retrieves an invoice.
- `list(array $options = [])` lists invoices with pagination/status filters.
- `cancel(string $invoiceId)` cancels a pending Live invoice; Sandbox cancellation uses the underlying Sandbox simulator.
- `paymentUrl(array $invoice)` validates the trusted Hosted Checkout URL.
- `HostedCheckout::redirect(array $invoice)` redirects to that URL.

Persist one stable idempotency key per merchant order before creating an invoice. Reuse it after timeouts or uncertain responses; use a new key for a new order. See [`examples/CheckoutController.php`](examples/CheckoutController.php). Its application `Order` model is illustrative and must be adapted.

## Webhooks

The package registers environment-specific endpoints:

- `POST /api/pembayaranku/webhook/live`
- `POST /api/pembayaranku/webhook/sandbox`

Configure each HTTPS URL and its corresponding environment secret in Dashboard → Developer. The receiver verifies the exact raw body using HMAC-SHA256 of `<timestamp>.<raw body>`, checks the signature timestamp and event ID/type headers, rejects cross-environment payloads, and rejects bodies over 256 KiB.

Implement `PembayaranKu\Laravel\Contracts\WebhookHandler` and set `PEMBAYARANKU_WEBHOOK_HANDLER` to its class. Event claim/deduplication and handler database work run in one transaction on Laravel's default connection. Keep the handler synchronous and idempotent; do not perform external network calls inside the database transaction. Duplicates receive HTTP 200, active claims receive HTTP 503 with `Retry-After`, and handler failures return HTTP 500 for retry. Example: [`examples/PembayaranKuWebhookHandler.php`](examples/PembayaranKuWebhookHandler.php).

Treat `payment.paid` and `invoice.status_changed` with invoice `status=paid` as the successful payment transition. For `payment.confirmation_changed`, read `invoice_status`, not `payment_confirmation`; `matched` and legacy `verified` are not invoice success. Deduplicate by event ID and by order/invoice because distinct events can describe the same paid transition. Finance is asynchronous and does not gate marking the order paid. `webhook.test` is acknowledged without calling the business handler.

## Sandbox and Live

Use the Sandbox API key and webhook secret together. Sandbox invoice status simulation may emit `invoice.status_changed`; it does not create real QRIS payments, Android Companion events, or Payment Matching records. Keep Live and Sandbox keys, webhook URLs, and secrets separate. Never test using a Live key or real customer payment.

## Compatibility and verification

- PHP 8.1+, Laravel 10–12, `ext-curl`, and `ext-json` through the required PHP SDK.
- Composer package metadata includes PSR-4 autoloading and Laravel service provider auto-discovery.
- Runtime verification against installed Laravel applications is not claimed by this source release.
- No refund support, payment-provider adapter, or browser-side API key support is included.

See [`CHANGELOG.md`](CHANGELOG.md) and the [Merchant API/webhook reference](https://www.pembayaranku.com/docs).
