<?php

declare(strict_types=1);

use MadaraCoder\LaravelEsewa\LaravelEsewa;
use Illuminate\Support\Facades\Http;

// ─── Helper ──────────────────────────────────────────────────────────────────

function esewa(
    string $environment = 'Sandbox',
    string $merchantId = 'EPAYTEST',
    string $secretKey = '8gBm/:&EnhH.1/q',
): LaravelEsewa {
    config([
        'esewa.env'        => $environment,
        'esewa.scd'        => $merchantId,
        'esewa.secret_key' => $secretKey,
    ]);

    return new LaravelEsewa();
}

// ─────────────────────────────────────────────────────────────────────────────
// V1 — esewaCheckout()
// ─────────────────────────────────────────────────────────────────────────────

describe('V1 — esewaCheckout()', function (): void {

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

// ─────────────────────────────────────────────────────────────────────────────
// V1 — verifyPayment()
// ─────────────────────────────────────────────────────────────────────────────

describe('V1 — verifyPayment()', function (): void {

    it('returns true when eSewa responds with Success', function (): void {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response('<response>Success</response>', 200),
        ]);

        expect(
            esewa()->verifyPayment(orderId: 'ORDER-001', amount: 100, referenceId: 'REF-123')
        )->toBeTrue();
    });

    it('returns false when eSewa responds with Failure', function (): void {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response('<response>Failure</response>', 200),
        ]);

        expect(
            esewa()->verifyPayment(orderId: 'ORDER-001', amount: 100, referenceId: 'REF-BAD')
        )->toBeFalse();
    });

    it('sends the correct POST parameters to eSewa', function (): void {
        Http::fake([
            'rc.esewa.com.np/epay/transrec' => Http::response('<response>Success</response>', 200),
        ]);

        esewa()->verifyPayment(orderId: 'ORDER-XYZ', amount: 200.00, referenceId: 'REF-XYZ');

        Http::assertSent(
            fn ($request) => $request['pid'] === 'ORDER-XYZ'
                && (float) $request['amt'] === 200.00
                && $request['rid'] === 'REF-XYZ'
                && $request['scd'] === 'EPAYTEST'
        );
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// V2 — generateFormData()
// ─────────────────────────────────────────────────────────────────────────────

describe('V2 — generateFormData()', function (): void {

    it('returns an array', function (): void {
        $data = esewa()->generateFormData(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($data)->toBeArray();
    });

    it('contains all required keys for the V2 form', function (): void {
        $data = esewa()->generateFormData(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($data)
            ->toHaveKeys([
                'amount',
                'tax_amount',
                'total_amount',
                'transaction_uuid',
                'product_code',
                'product_service_charge',
                'product_delivery_charge',
                'success_url',
                'failure_url',
                'signed_field_names',
                'signature',
                'payment_url',
            ]);
    });

    it('sets the correct product code', function (): void {
        $data = esewa()->generateFormData(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($data['product_code'])->toBe('EPAYTEST');
    });

    it('calculates total_amount correctly with all charges', function (): void {
        $data = esewa()->generateFormData(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
            taxAmount: 10,
            serviceCharge: 5,
            deliveryCharge: 20,
        );

        // total = 100 + 10 + 5 + 20 = 135
        expect($data['total_amount'])->toBe(135.0);
    });

    it('generates a non-empty HMAC signature', function (): void {
        $data = esewa()->generateFormData(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($data['signature'])
            ->toBeString()
            ->not->toBeEmpty();
    });

    it('generates a correct HMAC-SHA256 signature', function (): void {
        $data = esewa()->generateFormData(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        // Manually re-generate the expected signature the same way the class does
        $message = "total_amount=100,transaction_uuid=ORDER-001,product_code=EPAYTEST";
        $expectedSignature = base64_encode(hash_hmac('sha256', $message, '8gBm/:&EnhH.1/q', true));

        expect($data['signature'])->toBe($expectedSignature);
    });

    it('uses signed_field_names in the correct order', function (): void {
        $data = esewa()->generateFormData(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($data['signed_field_names'])->toBe('total_amount,transaction_uuid,product_code');
    });

    it('uses the sandbox payment URL in Sandbox environment', function (): void {
        $data = esewa()->generateFormData(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($data['payment_url'])->toContain('rc-epay.esewa.com.np');
    });

    it('uses the live payment URL in Live environment', function (): void {
        $data = esewa(environment: 'Live', merchantId: 'LIVE_SCD')->generateFormData(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );

        expect($data['payment_url'])
            ->toContain('epay.esewa.com.np')
            ->not->toContain('rc-epay');
    });

    it('throws an exception when secret key is empty', function (): void {
        esewa(secretKey: '')->generateFormData(
            amount: 100,
            orderId: 'ORDER-001',
            successUrl: 'https://myapp.com/success',
            failureUrl: 'https://myapp.com/failure',
        );
    })->throws(\Exception::class, 'eSewa secret key is missing');

});

// ─────────────────────────────────────────────────────────────────────────────
// V2 — verifyV2Payment()
// ─────────────────────────────────────────────────────────────────────────────

describe('V2 — verifyV2Payment()', function (): void {

    /**
     * Helper: build a real Base64 encoded response the way eSewa would send it.
     */
    function buildEsewaV2Response(
        string $status = 'COMPLETE',
        string $transactionUuid = 'ORDER-001',
        float $totalAmount = 100,
        string $productCode = 'EPAYTEST',
        string $secretKey = '8gBm/:&EnhH.1/q',
    ): string {
        $signedFieldNames = 'transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names';

        $message = "transaction_code=TXN-001,status={$status},total_amount={$totalAmount}"
            . ",transaction_uuid={$transactionUuid},product_code={$productCode}"
            . ",signed_field_names={$signedFieldNames}";

        $signature = base64_encode(hash_hmac('sha256', $message, $secretKey, true));

        $responseData = [
            'transaction_code'   => 'TXN-001',
            'status'             => $status,
            'total_amount'       => $totalAmount,
            'transaction_uuid'   => $transactionUuid,
            'product_code'       => $productCode,
            'signed_field_names' => $signedFieldNames,
            'signature'          => $signature,
        ];

        return base64_encode(json_encode($responseData));
    }

    it('returns true for a valid COMPLETE response', function (): void {
        $encodedData = buildEsewaV2Response(status: 'COMPLETE');

        expect(esewa()->verifyV2Payment($encodedData))->toBeTrue();
    });

    it('returns false when status is not COMPLETE', function (): void {
        $encodedData = buildEsewaV2Response(status: 'PENDING');

        expect(esewa()->verifyV2Payment($encodedData))->toBeFalse();
    });

    it('returns false when the signature has been tampered with', function (): void {
        $encodedData = buildEsewaV2Response(status: 'COMPLETE');

        // Decode, tamper with signature, re-encode
        $decoded = json_decode(base64_decode($encodedData), true);
        $decoded['signature'] = 'tampered-signature-value';
        $tamperedData = base64_encode(json_encode($decoded));

        expect(esewa()->verifyV2Payment($tamperedData))->toBeFalse();
    });

    it('returns false when the total amount has been tampered with', function (): void {
        // Build response with amount 100, but tamper it to 1 to simulate fraud
        $encodedData = buildEsewaV2Response(status: 'COMPLETE', totalAmount: 100);

        $decoded = json_decode(base64_decode($encodedData), true);
        $decoded['total_amount'] = 1; // attacker changes amount
        $tamperedData = base64_encode(json_encode($decoded));

        // Signature will no longer match because amount changed
        expect(esewa()->verifyV2Payment($tamperedData))->toBeFalse();
    });

    it('returns false for an empty or invalid Base64 string', function (): void {
        expect(esewa()->verifyV2Payment('not-valid-base64$$$$'))->toBeFalse();
    });

    it('throws an exception when secret key is empty', function (): void {
        $encodedData = buildEsewaV2Response();
        esewa(secretKey: '')->verifyV2Payment($encodedData);
    })->throws(\Exception::class, 'eSewa secret key is missing');

});

// ─────────────────────────────────────────────────────────────────────────────
// V2 — checkStatus()
// ─────────────────────────────────────────────────────────────────────────────

describe('V2 — checkStatus()', function (): void {

    it('returns COMPLETE status for a paid order', function (): void {
        Http::fake([
            'uat.esewa.com.np/api/epay/transaction/status/*' => Http::response([
                'pid'         => 'ORDER-001',
                'scd'         => 'EPAYTEST',
                'totalAmount' => 100.0,
                'status'      => 'COMPLETE',
                'refId'       => 'TXN-ABC',
            ], 200),
        ]);

        $status = esewa()->checkStatus(orderId: 'ORDER-001', totalAmount: 100);

        expect($status['status'])->toBe('COMPLETE');
        expect($status['refId'])->toBe('TXN-ABC');
    });

    it('returns PENDING status for an incomplete order', function (): void {
        Http::fake([
            'uat.esewa.com.np/api/epay/transaction/status/*' => Http::response([
                'pid'         => 'ORDER-002',
                'scd'         => 'EPAYTEST',
                'totalAmount' => 200.0,
                'status'      => 'PENDING',
                'refId'       => null,
            ], 200),
        ]);

        $status = esewa()->checkStatus(orderId: 'ORDER-002', totalAmount: 200);

        expect($status['status'])->toBe('PENDING');
        expect($status['refId'])->toBeNull();
    });

    it('returns NOT_FOUND for an expired session', function (): void {
        Http::fake([
            'uat.esewa.com.np/api/epay/transaction/status/*' => Http::response([
                'pid'         => 'ORDER-003',
                'scd'         => 'EPAYTEST',
                'totalAmount' => 150.0,
                'status'      => 'NOT_FOUND',
                'refId'       => null,
            ], 200),
        ]);

        $status = esewa()->checkStatus(orderId: 'ORDER-003', totalAmount: 150);

        expect($status['status'])->toBe('NOT_FOUND');
    });

    it('sends the correct query parameters to eSewa', function (): void {
        Http::fake([
            'uat.esewa.com.np/api/epay/transaction/status/*' => Http::response([
                'status' => 'COMPLETE',
            ], 200),
        ]);

        esewa()->checkStatus(orderId: 'ORDER-XYZ', totalAmount: 500);

        Http::assertSent(
            fn ($request) => $request['product_code'] === 'EPAYTEST'
                && $request['transaction_uuid'] === 'ORDER-XYZ'
                && (float) $request['total_amount'] === 500.0
        );
    });

    it('uses the live status URL in Live environment', function (): void {
        Http::fake([
            'epay.esewa.com.np/api/epay/transaction/status/*' => Http::response([
                'status' => 'COMPLETE',
            ], 200),
        ]);

        esewa(environment: 'Live', merchantId: 'LIVE_SCD')->checkStatus(
            orderId: 'ORDER-001',
            totalAmount: 100,
        );

        Http::assertSent(
            fn ($request) => str_contains($request->url(), 'epay.esewa.com.np')
                && ! str_contains($request->url(), 'uat.')
        );
    });

});
