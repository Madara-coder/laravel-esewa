<?php

declare(strict_types=1);

use MadaraCoder\LaravelEsewa\Facades\Esewa;
use Illuminate\Support\Facades\Http;

// ─────────────────────────────────────────────────────────────────────────────
// V1 Facade Tests
// ─────────────────────────────────────────────────────────────────────────────

describe('Facade — V1 esewaCheckout()', function (): void {

    it('generates a V1 checkout URL via the Facade', function (): void {
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

    it('verifies a successful V1 payment via the Facade', function (): void {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response('<response>Success</response>', 200),
        ]);

        expect(
            Esewa::verifyPayment(orderId: 'FACADE-ORDER-001', amount: 100, referenceId: 'REF-123')
        )->toBeTrue();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// V2 Facade Tests
// ─────────────────────────────────────────────────────────────────────────────

describe('Facade — V2 generateFormData()', function (): void {

    it('generates V2 form data via the Facade', function (): void {
        $data = Esewa::generateFormData(
            amount: 100,
            orderId: 'FACADE-V2-ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($data)
            ->toBeArray()
            ->toHaveKeys(['signature', 'payment_url', 'transaction_uuid'])
            ->and($data['transaction_uuid'])->toBe('FACADE-V2-ORDER-001')
            ->and($data['payment_url'])->toContain('rc-epay.esewa.com.np');
    });

    it('verifies a V2 payment response via the Facade', function (): void {
        // Build a valid eSewa V2 response the way eSewa would send it
        $signedFieldNames = 'transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names';
        $message = "transaction_code=TXN-001,status=COMPLETE,total_amount=100"
            . ",transaction_uuid=ORDER-001,product_code=EPAYTEST"
            . ",signed_field_names={$signedFieldNames}";

        $signature = base64_encode(hash_hmac('sha256', $message, '8gBm/:&EnhH.1/q', true));

        $responseData = [
            'transaction_code'   => 'TXN-001',
            'status'             => 'COMPLETE',
            'total_amount'       => 100,
            'transaction_uuid'   => 'ORDER-001',
            'product_code'       => 'EPAYTEST',
            'signed_field_names' => $signedFieldNames,
            'signature'          => $signature,
        ];

        $encodedData = base64_encode(json_encode($responseData));

        expect(Esewa::verifyV2Payment($encodedData))->toBeTrue();
    });

    it('checks transaction status via the Facade', function (): void {
        Http::fake([
            'uat.esewa.com.np/api/epay/transaction/status/*' => Http::response([
                'status' => 'COMPLETE',
                'refId'  => 'TXN-XYZ',
            ], 200),
        ]);

        $status = Esewa::checkStatus(orderId: 'FACADE-ORDER-001', totalAmount: 100);

        expect($status['status'])->toBe('COMPLETE');
    });

});
