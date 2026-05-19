<?php

use MadaraCoder\LaravelEsewa\Facades\Esewa;
use Illuminate\Support\Facades\Http;

// ─── Facade: esewaCheckout() ──────────────────────────────────────────────────

describe('Esewa Facade - esewaCheckout()', function () {

    it('can generate a checkout URL via the Facade', function () {
        $url = Esewa::esewaCheckout(
            amount: 100,
            order_id: 'FACADE-ORDER-001',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
        );

        expect($url)
            ->toBeString()
            ->toContain('rc.esewa.com.np')
            ->toContain('pid=FACADE-ORDER-001')
            ->toContain('scd=EPAYTEST');
    });

    it('calculates total correctly via the Facade', function () {
        $url = Esewa::esewaCheckout(
            amount: 100,
            order_id: 'FACADE-ORDER-002',
            su: 'https://myapp.com/success',
            fu: 'https://myapp.com/failure',
            tax_amount: 5,
            service_charge: 5,
            delivery_charge: 10,
        );

        // tAmt = 100 + 5 + 5 + 10 = 120
        expect($url)->toContain('tAmt=120');
    });

});

// ─── Facade: verifyPayment() ─────────────────────────────────────────────────

describe('Esewa Facade - verifyPayment()', function () {

    it('returns true for a successful payment via the Facade', function () {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response(
                '<response>Success</response>',
                200
            ),
        ]);

        $result = Esewa::verifyPayment(
            oid: 'FACADE-ORDER-001',
            amt: 100,
            refId: 'REF-FACADE-123',
        );

        expect($result)->toBeTrue();
    });

    it('returns false for a failed payment via the Facade', function () {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response(
                '<response>Failure</response>',
                200
            ),
        ]);

        $result = Esewa::verifyPayment(
            oid: 'FACADE-ORDER-001',
            amt: 100,
            refId: 'REF-BAD',
        );

        expect($result)->toBeFalse();
    });

});
