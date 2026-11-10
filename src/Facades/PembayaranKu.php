<?php

declare(strict_types=1);

namespace PembayaranKu\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/** @method static array create(array $input, string $idempotencyKey) @method static array retrieve(string $invoiceId) @method static array list(array $options = []) @method static array cancel(string $invoiceId) */
final class PembayaranKu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PembayaranKu\Laravel\InvoiceService::class;
    }
}
