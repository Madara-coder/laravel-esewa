<?php

declare(strict_types=1);

use MadaraCoder\LaravelEsewa\LaravelEsewa;
use Illuminate\Support\Facades\Http;

// ─── Helper ──────────────────────────────────────────────────────────────────

/**
 * Create a fresh LaravelEsewa instance with the given config.
 */
function esewa(string $environment = 'Sandbox', string $merchantId = 'EPAYTEST'): LaravelEsewa
{
    config(['esewa.env' => $environment, 'esewa.scd' => $merchantId]);

    return new LaravelEsewa();
}

// ─── esewaCheckout() ─────────────────────────────────────────────────────────

describe('esewaCheckout()', function (): void {

    it('returns a string URL', function (): void {
        $url = esewa()->esewaCheckout(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($url)->toBeString();
    });

    it('uses the sandbox domain in Sandbox environment', function (): void {
        $url = esewa()->esewaCheckout(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($url)
            ->toContain('rc.esewa.com.np')
            ->not->toContain('esewa.com.np/epay/main/?');
    });

    it('uses the live domain in Live environment', function (): void {
        $url = esewa(environment: 'Live', merchantId: 'LIVE_SCD')->esewaCheckout(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($url)
            ->toContain('esewa.com.np/epay/main/?')
            ->not->toContain('rc.esewa.com.np');
    });

    it('includes the order ID as pid in the URL', function (): void {
        $url = esewa()->esewaCheckout(
            amount: 100,
            orderId: 'MY-ORDER-999',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($url)->toContain('pid=MY-ORDER-999');
    });

    it('includes the merchant code as scd in the URL', function (): void {
        $url = esewa()->esewaCheckout(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($url)->toContain('scd=EPAYTEST');
    });

    it('includes the base amount in the URL', function (): void {
        $url = esewa()->esewaCheckout(
            amount: 500,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($url)->toContain('amt=500');
    });

    it('includes the success and failure URLs', function (): void {
        $url = esewa()->esewaCheckout(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($url)
            ->toContain('su=')
            ->toContain('fu=');
    });

    it('calculates tAmt correctly with all charges', function (): void {
        $url = esewa()->esewaCheckout(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
            taxAmount: 10,
            serviceCharge: 5,
            deliveryCharge: 20,
        );

        // tAmt = 100 + 10 + 5 + 20 = 135
        expect($url)->toContain('tAmt=135');
    });

    it('sets tAmt equal to amount when no extra charges are provided', function (): void {
        $url = esewa()->esewaCheckout(
            amount: 250,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($url)->toContain('tAmt=250');
    });

    it('defaults all optional charges to zero', function (): void {
        $url = esewa()->esewaCheckout(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($url)
            ->toContain('txAmt=0')
            ->toContain('psc=0')
            ->toContain('pdc=0');
    });

    it('includes all individual charge parameters correctly', function (): void {
        $url = esewa()->esewaCheckout(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
            taxAmount: 8,
            serviceCharge: 12,
            deliveryCharge: 30,
        );

        expect($url)
            ->toContain('amt=100')
            ->toContain('txAmt=8')
            ->toContain('psc=12')
            ->toContain('pdc=30')
            ->toContain('tAmt=150');
    });

    it('throws an exception for an invalid environment', function (): void {
        esewa(environment: 'Production')->esewaCheckout(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );
    })->throws(\Exception::class, "Invalid eSewa environment");

    it('throws an exception when merchant ID is empty', function (): void {
        esewa(merchantId: '')->esewaCheckout(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );
    })->throws(\Exception::class, 'eSewa merchant ID (SCD) is missing');

});

// ─── verifyPayment() ─────────────────────────────────────────────────────────

describe('verifyPayment()', function (): void {

    it('returns true when eSewa responds with Success', function (): void {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response(
                '<response>Success</response>',
                200
            ),
        ]);

        $result = esewa()->verifyPayment(
            orderId: 'ORDER-001',
            amount: 100,
            referenceId: 'REF-ABC123',
        );

        expect($result)->toBeTrue();
    });

    it('returns false when eSewa responds with Failure', function (): void {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response(
                '<response>Failure</response>',
                200
            ),
        ]);

        $result = esewa()->verifyPayment(
            orderId: 'ORDER-001',
            amount: 100,
            referenceId: 'REF-INVALID',
        );

        expect($result)->toBeFalse();
    });

    it('calls the sandbox verify URL in Sandbox environment', function (): void {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response(
                '<response>Success</response>',
                200
            ),
        ]);

        esewa()->verifyPayment(
            orderId: 'ORDER-001',
            amount: 100,
            referenceId: 'REF-123',
        );

        Http::assertSent(
            fn ($request) => str_contains($request->url(), 'rc.esewa.com.np/epay/transrec')
        );
    });

    it('calls the live verify URL in Live environment', function (): void {
        Http::fake([
            'esewa.com.np/epay/transrec' => Http::response(
                '<response>Success</response>',
                200
            ),
        ]);

        esewa(environment: 'Live', merchantId: 'LIVE_SCD')->verifyPayment(
            orderId: 'ORDER-001',
            amount: 100,
            referenceId: 'REF-123',
        );

        Http::assertSent(
            fn ($request) => str_contains($request->url(), 'esewa.com.np/epay/transrec')
                && ! str_contains($request->url(), 'rc.')
        );
    });

    it('sends the correct POST parameters to eSewa', function (): void {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response(
                '<response>Success</response>',
                200
            ),
        ]);

        esewa()->verifyPayment(
            orderId: 'ORDER-XYZ',
            amount: 200.00,
            referenceId: 'REF-XYZ',
        );

        Http::assertSent(
            fn ($request) => $request['pid'] === 'ORDER-XYZ'
                && (float) $request['amt'] === 200.00
                && $request['rid'] === 'REF-XYZ'
                && $request['scd'] === 'EPAYTEST'
        );
    });

    it('throws an exception when merchant ID is empty', function (): void {
        esewa(merchantId: '')->verifyPayment(
            orderId: 'ORDER-001',
            amount: 100,
            referenceId: 'REF-123',
        );
    })->throws(\Exception::class, 'eSewa merchant ID (SCD) is missing');

});
