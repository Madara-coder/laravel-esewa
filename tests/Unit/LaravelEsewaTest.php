<?php

use MadaraCoder\LaravelEsewa\LaravelEsewa;

// ─── Helper ──────────────────────────────────────────────────────────────────

/**
 * Create a fresh LaravelEsewa instance with custom config.
 */
function esewa(string $env = 'Sandbox', string $scd = 'EPAYTEST'): LaravelEsewa
{
    config(['esewa.env' => $env, 'esewa.scd' => $scd]);
    return new LaravelEsewa();
}

// ─── esewaCheckout() ─────────────────────────────────────────────────────────

describe('esewaCheckout()', function () {

    it('returns a string URL', function () {
        $url = esewa()->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-001',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );

        expect($url)->toBeString();
    });

    it('points to the sandbox domain in Sandbox environment', function () {
        $url = esewa(env: 'Sandbox')->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-001',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );

        expect($url)
            ->toContain('rc.esewa.com.np')
            ->not->toContain('esewa.com.np/epay/main/?');
    });

    it('points to the live domain in Live environment', function () {
        $url = esewa(env: 'Live', scd: 'LIVE_MERCHANT_CODE')->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-001',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );

        expect($url)
            ->toContain('esewa.com.np/epay/main/?')
            ->not->toContain('rc.esewa.com.np');
    });

    it('includes the correct amount in the URL', function () {
        $url = esewa()->esewaCheckout(
            amount: 500,
            order_id: 'ORDER-002',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );

        expect($url)->toContain('amt=500');
    });

    it('includes the order ID (pid) in the URL', function () {
        $url = esewa()->esewaCheckout(
            amount: 100,
            order_id: 'MY-ORDER-999',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );

        expect($url)->toContain('pid=MY-ORDER-999');
    });

    it('includes the merchant ID (scd) in the URL', function () {
        $url = esewa(scd: 'EPAYTEST')->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-003',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );

        expect($url)->toContain('scd=EPAYTEST');
    });

    it('includes the success and failure URLs', function () {
        $url = esewa()->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-004',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );

        expect($url)
            ->toContain('su=')
            ->toContain('fu=');
    });

    it('calculates total amount (tAmt) correctly with all charges', function () {
        $url = esewa()->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-005',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
            tax_amount: 10,
            service_charge: 5,
            delivery_charge: 20,
        );

        // tAmt = 100 + 10 + 5 + 20 = 135
        expect($url)->toContain('tAmt=135');
    });

    it('sets tAmt equal to amount when no extra charges are given', function () {
        $url = esewa()->esewaCheckout(
            amount: 250,
            order_id: 'ORDER-006',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );

        expect($url)->toContain('tAmt=250');
    });

    it('defaults all optional charges to zero', function () {
        $url = esewa()->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-007',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );

        expect($url)
            ->toContain('txAmt=0')
            ->toContain('psc=0')
            ->toContain('pdc=0');
    });

    it('includes all individual charge parameters in the URL', function () {
        $url = esewa()->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-008',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
            tax_amount: 8,
            service_charge: 12,
            delivery_charge: 30,
        );

        expect($url)
            ->toContain('amt=100')
            ->toContain('txAmt=8')
            ->toContain('psc=12')
            ->toContain('pdc=30')
            ->toContain('tAmt=150');
    });

    it('throws an exception for an invalid environment', function () {
        esewa(env: 'Production')->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-009',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );
    })->throws(\Exception::class, "Invalid eSewa environment");

    it('throws an exception when merchant ID is empty', function () {
        esewa(scd: '')->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-010',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );
    })->throws(\Exception::class, "eSewa merchant ID (SCD) is missing");

});

// ─── verifyPayment() ──────────────────────────────────────────────────────────

describe('verifyPayment()', function () {

    it('returns true when eSewa responds with Success', function () {
        \Illuminate\Support\Facades\Http::fake([
            'rc.esewa.com.np/epay/transrec' => \Illuminate\Support\Facades\Http::response(
                '<response>Success</response>',
                200
            ),
        ]);

        $result = esewa()->verifyPayment(
            oid: 'ORDER-001',
            amt: 100,
            refId: 'REF-ABC123',
        );

        expect($result)->toBeTrue();
    });

    it('returns false when eSewa responds with Failure', function () {
        \Illuminate\Support\Facades\Http::fake([
            'rc.esewa.com.np/epay/transrec' => \Illuminate\Support\Facades\Http::response(
                '<response>Failure</response>',
                200
            ),
        ]);

        $result = esewa()->verifyPayment(
            oid: 'ORDER-001',
            amt: 100,
            refId: 'REF-INVALID',
        );

        expect($result)->toBeFalse();
    });

    it('calls the sandbox verify URL in Sandbox environment', function () {
        \Illuminate\Support\Facades\Http::fake([
            'rc.esewa.com.np/epay/transrec' => \Illuminate\Support\Facades\Http::response(
                '<response>Success</response>',
                200
            ),
        ]);

        esewa(env: 'Sandbox')->verifyPayment('ORDER-001', 100, 'REF-123');

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), 'rc.esewa.com.np/epay/transrec');
        });
    });

    it('calls the live verify URL in Live environment', function () {
        \Illuminate\Support\Facades\Http::fake([
            'esewa.com.np/epay/transrec' => \Illuminate\Support\Facades\Http::response(
                '<response>Success</response>',
                200
            ),
        ]);

        esewa(env: 'Live', scd: 'LIVE_SCD')->verifyPayment('ORDER-001', 100, 'REF-123');

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), 'esewa.com.np/epay/transrec')
                && !str_contains($request->url(), 'rc.');
        });
    });

    it('sends the correct POST parameters to eSewa', function () {
        \Illuminate\Support\Facades\Http::fake([
            'rc.esewa.com.np/epay/transrec' => \Illuminate\Support\Facades\Http::response(
                '<response>Success</response>',
                200
            ),
        ]);

        esewa()->verifyPayment('ORDER-XYZ', 200.00, 'REF-XYZ');

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return $request['pid'] === 'ORDER-XYZ'
                && (float) $request['amt'] === 200.00
                && $request['rid'] === 'REF-XYZ'
                && $request['scd'] === 'EPAYTEST';
        });
    });

    it('throws an exception when merchant ID is empty', function () {
        esewa(scd: '')->verifyPayment('ORDER-001', 100, 'REF-123');
    })->throws(\Exception::class, "eSewa merchant ID (SCD) is missing");

});
