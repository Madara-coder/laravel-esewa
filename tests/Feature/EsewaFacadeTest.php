<?php

declare(strict_types=1);

use MadaraCoder\LaravelEsewa\Facades\Esewa;
use Illuminate\Support\Facades\Http;

// ─── Facade: esewaCheckout() ─────────────────────────────────────────────────

describe('Esewa Facade - esewaCheckout()', function (): void {

    it('generates a checkout URL via the Facade', function (): void {
        $url = Esewa::esewaCheckout(
            amount: 100,
            orderId: 'FACADE-ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($url)
            ->toBeString()
            ->toContain('rc.esewa.com.np')
            ->toContain('pid=FACADE-ORDER-001')
            ->toContain('scd=EPAYTEST');
    });

    it('calculates total amount correctly via the Facade', function (): void {
        $url = Esewa::esewaCheckout(
            amount: 100,
            orderId: 'FACADE-ORDER-002',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
            taxAmount: 5,
            serviceCharge: 5,
            deliveryCharge: 10,
        );

        // tAmt = 100 + 5 + 5 + 10 = 120
        expect($url)->toContain('tAmt=120');
    });

});

// ─── Facade: verifyPayment() ─────────────────────────────────────────────────

describe('Esewa Facade - verifyPayment()', function (): void {

    it('returns true for a successful payment via the Facade', function (): void {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response(
                '<response>Success</response>',
                200
            ),
        ]);

        $result = Esewa::verifyPayment(
            orderId: 'FACADE-ORDER-001',
            amount: 100,
            referenceId: 'REF-FACADE-123',
        );

        expect($result)->toBeTrue();
    });

    it('returns false for a failed payment via the Facade', function (): void {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response(
                '<response>Failure</response>',
                200
            ),
        ]);

        $result = Esewa::verifyPayment(
            orderId: 'FACADE-ORDER-001',
            amount: 100,
            referenceId: 'REF-BAD',
        );

        expect($result)->toBeFalse();
    });

});
