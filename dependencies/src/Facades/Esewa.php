<?php

namespace MadaraCoder\LaravelEsewa\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string esewaCheckout(float $amount, string $order_id, string $su, string $fu, float $tax_amount = 0, float $service_charge = 0, float $delivery_charge = 0)
 * @method static bool   verifyPayment(string $oid, float $amt, string $refId)
 *
 * @see \MadaraCoder\LaravelEsewa\LaravelEsewa
 */
class Esewa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-esewa';
    }
}
