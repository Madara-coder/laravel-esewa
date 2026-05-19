<?php

declare(strict_types=1);

namespace MadaraCoder\LaravelEsewa\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string esewaCheckout(float $amount, string $orderId, string $successUrl, string $failureUrl, float $taxAmount = 0, float $serviceCharge = 0, float $deliveryCharge = 0)
 * @method static bool   verifyPayment(string $orderId, float $amount, string $referenceId)
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
