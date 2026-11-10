<?php

declare(strict_types=1);

namespace PembayaranKu\Laravel;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PembayaranKu\Client;

final class PembayaranKuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/pembayaranku.php', 'pembayaranku');
        $this->app->singleton(Client::class, static function (): Client {
            $apiKey = (string) config('pembayaranku.api_key', '');
            $environment = (string) config('pembayaranku.environment', 'sandbox');
            $prefix = $environment === 'live' ? 'pk_live_' : 'pk_test_';
            if (!in_array($environment, ['live', 'sandbox'], true) || !str_starts_with($apiKey, $prefix)) {
                throw new InvalidArgumentException('PembayaranKU API key must match PEMBAYARANKU_ENVIRONMENT.');
            }
            return new Client(
                $apiKey,
                $environment,
                (string) config('pembayaranku.base_url'),
                (int) config('pembayaranku.timeout_seconds', 15),
            );
        });
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(HostedCheckout::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/pembayaranku.php' => config_path('pembayaranku.php')], 'pembayaranku-config');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}
