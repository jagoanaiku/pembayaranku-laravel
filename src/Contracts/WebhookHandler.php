<?php

declare(strict_types=1);

namespace PembayaranKu\Laravel\Contracts;

interface WebhookHandler
{
    /**
     * Handle a verified event synchronously. Use eventId for idempotent business updates.
     * Keep database changes on Laravel's default connection so they share the package transaction.
     *
     * @param array<string, mixed> $event
     */
    public function handle(array $event, string $eventId, string $environment): void;
}
