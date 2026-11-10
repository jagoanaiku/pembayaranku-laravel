<?php

use Illuminate\Support\Facades\Route;
use PembayaranKu\Laravel\Http\Controllers\WebhookController;

Route::prefix('api')->middleware('api')->group(static function (): void {
    Route::post('/pembayaranku/webhook/{environment}', WebhookController::class)
        ->whereIn('environment', ['live', 'sandbox'])
        ->name('pembayaranku.webhook');
});
