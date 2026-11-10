<?php

declare(strict_types=1);

namespace PembayaranKu\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PembayaranKu\Laravel\Contracts\WebhookHandler;
use Throwable;

final class WebhookController
{
    public function __invoke(Request $request, string $environment): JsonResponse
    {
        if (!in_array($environment, ['live', 'sandbox'], true)) {
            return response()->json(['error' => 'invalid_environment'], 404);
        }
        $rawBody = $request->getContent();
        if (strlen($rawBody) > (int) config('pembayaranku.webhooks.max_body_bytes', 262144)) {
            return response()->json(['error' => 'payload_too_large'], 413);
        }
        $secret = (string) config('pembayaranku.webhooks.secrets.' . $environment, '');
        if ($secret === '') {
            return response()->json(['error' => 'webhook_not_configured'], 503);
        }
        if (!$this->verifySignature($request, $rawBody, $secret)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event) || !is_string($event['id'] ?? null) || !is_string($event['type'] ?? null) || !is_array($event['data'] ?? null)) {
            return response()->json(['error' => 'invalid_payload'], 400);
        }
        $eventId = (string) $request->header('X-PembayaranKu-Event-Id', '');
        $eventType = (string) $request->header('X-PembayaranKu-Event', '');
        if (!preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $eventId)
            || !hash_equals($event['id'], $eventId) || !hash_equals($event['type'], $eventType)) {
            return response()->json(['error' => 'event_header_mismatch'], 400);
        }
        if ($eventType === 'webhook.test' || !in_array($eventType, ['invoice.status_changed', 'payment.confirmation_changed', 'payment.paid'], true)) {
            return response()->json(['received' => true, 'ignored' => true]);
        }
        if (!is_string($event['data']['invoice_id'] ?? null)
            || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $event['data']['invoice_id'])) {
            return response()->json(['error' => 'invalid_invoice_id'], 400);
        }
        $payloadEnvironment = $event['data']['environment'] ?? null;
        $expectedPayloadEnvironment = $environment === 'sandbox' ? 'test' : 'live';
        if ($payloadEnvironment !== $expectedPayloadEnvironment) {
            return response()->json(['error' => 'event_environment_mismatch'], 400);
        }

        $handlerClass = config('pembayaranku.webhooks.handler');
        if (!is_string($handlerClass) || $handlerClass === '') {
            return response()->json(['error' => 'webhook_handler_not_configured'], 503);
        }
        $payloadHash = hash('sha256', $rawBody);
        try {
            $result = DB::transaction(function () use ($event, $eventId, $eventType, $environment, $handlerClass, $payloadHash): string {
                $table = 'pembayaranku_webhook_events';
                DB::table($table)->insertOrIgnore([
                    'event_id' => $eventId,
                    'environment' => $environment,
                    'event_type' => $eventType,
                    'payload_hash' => $payloadHash,
                    'state' => 'processing',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table($table)->where('event_id', $eventId)->lockForUpdate()->first();
                if (!$row) {
                    throw new \RuntimeException('Could not reserve webhook event.');
                }
                if (!hash_equals((string) $row->payload_hash, $payloadHash) || $row->environment !== $environment) {
                    return 'conflict';
                }
                if ($row->state === 'processed') {
                    return 'duplicate';
                }
                $staleBefore = now()->subSeconds((int) config('pembayaranku.webhooks.stale_processing_seconds', 180));
                if ($row->state === 'processing' && $row->updated_at >= $staleBefore->toDateTimeString()) {
                    return 'busy';
                }
                DB::table($table)->where('event_id', $eventId)->update(['state' => 'processing', 'updated_at' => now()]);
                $handler = app($handlerClass);
                if (!$handler instanceof WebhookHandler) {
                    throw new \RuntimeException('Configured webhook handler must implement WebhookHandler.');
                }
                $handler->handle($event, $eventId, $environment);
                DB::table($table)->where('event_id', $eventId)->update(['state' => 'processed', 'updated_at' => now()]);
                return 'processed';
            });
        } catch (Throwable $exception) {
            Log::error('PembayaranKU webhook handler failed.', ['event_id' => $eventId, 'environment' => $environment]);
            try {
                DB::table('pembayaranku_webhook_events')->updateOrInsert(
                    ['event_id' => $eventId],
                    ['environment' => $environment, 'event_type' => $eventType, 'payload_hash' => $payloadHash, 'state' => 'failed', 'updated_at' => now(), 'created_at' => now()]
                );
            } catch (Throwable) {
                // Keep the response retryable even if the database is unavailable.
            }
            return response()->json(['error' => 'event_processing_failed'], 500);
        }
        if ($result === 'conflict') {
            return response()->json(['error' => 'event_id_payload_conflict'], 409);
        }
        if ($result === 'busy') {
            return response()->json(['error' => 'event_processing'], 503, ['Retry-After' => '30']);
        }
        return response()->json(['received' => true, 'duplicate' => $result === 'duplicate']);
    }

    private function verifySignature(Request $request, string $rawBody, string $secret): bool
    {
        $signature = (string) $request->header('X-PembayaranKu-Signature', '');
        $timestamp = (string) $request->header('X-PembayaranKu-Timestamp', '');
        if (!preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/i', $signature, $matches)
            || !ctype_digit($timestamp) || !hash_equals($matches[1], $timestamp)
            || abs(time() - (int) $timestamp) > (int) config('pembayaranku.webhooks.max_clock_skew_seconds', 300)) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret), strtolower($matches[2]));
    }
}
