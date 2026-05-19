<?php

declare(strict_types=1);

namespace MadaraCoder\LaravelEsewa;

use Illuminate\Support\Facades\Http;

class LaravelEsewa
{
    private string $merchantId;

    private string $secretKey;

    private string $environment;

    // ─── V1 URLs ─────────────────────────────────────────────────────────────

    private const V1_SANDBOX_PAYMENT_URL = 'https://rc.esewa.com.np/epay/main?';

    private const V1_LIVE_PAYMENT_URL = 'https://esewa.com.np/epay/main/?';

    private const V1_SANDBOX_VERIFY_URL = 'https://rc.esewa.com.np/epay/transrec';

    private const V1_LIVE_VERIFY_URL = 'https://esewa.com.np/epay/transrec';

    // ─── V2 URLs ─────────────────────────────────────────────────────────────

    private const V2_SANDBOX_PAYMENT_URL = 'https://rc-epay.esewa.com.np/api/epay/main/v2/form';

    private const V2_LIVE_PAYMENT_URL = 'https://epay.esewa.com.np/api/epay/main/v2/form';

    private const V2_SANDBOX_STATUS_URL = 'https://uat.esewa.com.np/api/epay/transaction/status/';

    private const V2_LIVE_STATUS_URL = 'https://epay.esewa.com.np/api/epay/transaction/status/';

    public function __construct()
    {
        $this->merchantId  = config('esewa.scd');
        $this->secretKey   = config('esewa.secret_key');
        $this->environment = config('esewa.env');
    }

    // =========================================================================
    // V1 — LEGACY API
    // =========================================================================

    /**
     * [V1] Generate the eSewa payment checkout URL.
     *
     * Redirect the user to this URL to initiate payment on eSewa.
     * This uses eSewa's older V1 API which does not require a secret key.
     *
     * @param  float   $amount          Base product or service amount (required)
     * @param  string  $orderId         Your unique order or transaction ID (required)
     * @param  string  $successUrl      URL eSewa redirects to on successful payment (required)
     * @param  string  $failureUrl      URL eSewa redirects to on failed payment (required)
     * @param  float   $taxAmount       Tax applied on the order (default: 0)
     * @param  float   $serviceCharge   Service charge applied on the order (default: 0)
     * @param  float   $deliveryCharge  Delivery charge applied on the order (default: 0)
     *
     * @return string Full eSewa V1 redirect URL
     *
     * @throws \Exception
     */
    public function esewaCheckout(
        float $amount,
        string $orderId,
        string $successUrl,
        string $failureUrl,
        float $taxAmount = 0,
        float $serviceCharge = 0,
        float $deliveryCharge = 0,
    ): string {
        $this->validateConfig();

        $totalAmount = $amount + $taxAmount + $serviceCharge + $deliveryCharge;

        $params = [
            'amt'   => $amount,
            'pdc'   => $deliveryCharge,
            'psc'   => $serviceCharge,
            'txAmt' => $taxAmount,
            'tAmt'  => $totalAmount,
            'pid'   => $orderId,
            'scd'   => $this->merchantId,
            'su'    => $successUrl,
            'fu'    => $failureUrl,
        ];

        return $this->getV1PaymentUrl() . http_build_query($params);
    }

    /**
     * [V1] Verify a payment with eSewa after the user is redirected back.
     *
     * Call this inside your success callback route.
     * eSewa returns: oid, amt, refId as query parameters in the callback URL.
     *
     * @param  string  $orderId      Order ID returned by eSewa (same as pid you sent)
     * @param  float   $amount       Total amount returned by eSewa
     * @param  string  $referenceId  Reference ID returned by eSewa
     *
     * @return bool True if payment is verified successfully
     *
     * @throws \Exception
     */
    public function verifyPayment(
        string $orderId,
        float $amount,
        string $referenceId,
    ): bool {
        $this->validateConfig();

        $response = Http::asForm()->post($this->getV1VerifyUrl(), [
            'amt' => $amount,
            'scd' => $this->merchantId,
            'rid' => $referenceId,
            'pid' => $orderId,
        ]);

        return str_contains($response->body(), 'Success');
    }

    // =========================================================================
    // V2 — NEW API (HMAC-SHA256)
    // =========================================================================

    /**
     * [V2] Generate signed form data for eSewa V2 payment.
     *
     * Unlike V1 which uses a redirect URL, V2 requires submitting an HTML form
     * with a cryptographic HMAC-SHA256 signature for security.
     *
     * Return this data to your frontend and submit it as a POST form
     * to eSewa's V2 payment URL.
     *
     * How the signature works:
     *   message   = "total_amount={x},transaction_uuid={x},product_code={x}"
     *   signature = base64(HMAC-SHA256(message, secretKey))
     *
     * @param  float   $amount          Base product or service amount (required)
     * @param  string  $orderId         Your unique order or transaction ID (required)
     * @param  string  $successUrl      URL eSewa redirects to on successful payment (required)
     * @param  string  $failureUrl      URL eSewa redirects to on failed payment (required)
     * @param  float   $taxAmount       Tax applied on the order (default: 0)
     * @param  float   $serviceCharge   Service charge applied on the order (default: 0)
     * @param  float   $deliveryCharge  Delivery charge applied on the order (default: 0)
     *
     * @return array{
     *     amount: float,
     *     tax_amount: float,
     *     total_amount: float,
     *     transaction_uuid: string,
     *     product_code: string,
     *     product_service_charge: float,
     *     product_delivery_charge: float,
     *     success_url: string,
     *     failure_url: string,
     *     signed_field_names: string,
     *     signature: string,
     *     payment_url: string
     * }
     *
     * @throws \Exception
     */
    public function generateFormData(
        float $amount,
        string $orderId,
        string $successUrl,
        string $failureUrl,
        float $taxAmount = 0,
        float $serviceCharge = 0,
        float $deliveryCharge = 0,
    ): array {
        $this->validateConfig();
        $this->validateSecretKey();

        $totalAmount = $amount + $taxAmount + $serviceCharge + $deliveryCharge;

        // eSewa V2 requires signed_field_names in this exact order
        $signedFieldNames = 'total_amount,transaction_uuid,product_code';

        // Build the message string exactly as eSewa requires:
        // "total_amount=100,transaction_uuid=ORDER-001,product_code=EPAYTEST"
        $message = "total_amount={$totalAmount},transaction_uuid={$orderId},product_code={$this->merchantId}";

        // Generate HMAC-SHA256 signature and encode as Base64
        $signature = base64_encode(
            hash_hmac('sha256', $message, $this->secretKey, true)
        );

        return [
            'amount'                  => $amount,
            'tax_amount'              => $taxAmount,
            'total_amount'            => $totalAmount,
            'transaction_uuid'        => $orderId,
            'product_code'            => $this->merchantId,
            'product_service_charge'  => $serviceCharge,
            'product_delivery_charge' => $deliveryCharge,
            'success_url'             => $successUrl,
            'failure_url'             => $failureUrl,
            'signed_field_names'      => $signedFieldNames,
            'signature'               => $signature,
            'payment_url'             => $this->getV2PaymentUrl(),
        ];
    }

    /**
     * [V2] Verify the payment response received from eSewa after redirect.
     *
     * After successful payment, eSewa redirects to your success URL with
     * a Base64 encoded JSON string in the 'data' query parameter.
     *
     * This method:
     *   1. Decodes the Base64 response from eSewa
     *   2. Re-generates the HMAC-SHA256 signature from the response fields
     *   3. Compares it with the signature eSewa sent — if they match, payment is genuine
     *
     * Usage in your success callback:
     *   $verified = $esewa->verifyV2Payment($request->query('data'));
     *
     * @param  string  $encodedData  The raw Base64 string from eSewa's callback (?data=...)
     *
     * @return bool True if the signature is valid and payment is complete
     *
     * @throws \Exception
     */
    public function verifyV2Payment(string $encodedData): bool
    {
        $this->validateConfig();
        $this->validateSecretKey();

        // Step 1: Decode the Base64 response from eSewa
        $decodedJson = base64_decode($encodedData);
        $responseData = json_decode($decodedJson, true);

        if (! $responseData || ! isset($responseData['signature'], $responseData['signed_field_names'])) {
            return false;
        }

        // Step 2: Re-generate the signature from the fields eSewa signed
        // signed_field_names tells us which fields to use and in what order
        $signedFieldNames = explode(',', $responseData['signed_field_names']);

        $messageParts = [];
        foreach ($signedFieldNames as $fieldName) {
            $fieldName = trim($fieldName);
            if (isset($responseData[$fieldName])) {
                $messageParts[] = "{$fieldName}={$responseData[$fieldName]}";
            }
        }

        $message = implode(',', $messageParts);

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $message, $this->secretKey, true)
        );

        // Step 3: Compare signatures — if they match, payment is genuine
        // Also confirm the transaction status is COMPLETE
        return hash_equals($expectedSignature, $responseData['signature'])
            && ($responseData['status'] ?? '') === 'COMPLETE';
    }

    /**
     * [V2] Check the status of a transaction at any time.
     *
     * Unlike V1 which only verifies at the moment of callback,
     * V2 lets you query eSewa anytime to get the current status.
     *
     * Possible statuses returned by eSewa:
     *   - COMPLETE       : Payment successful
     *   - PENDING        : Payment initiated but not completed
     *   - FULL_REFUND    : Full payment refunded to customer
     *   - PARTIAL_REFUND : Partial payment refunded
     *   - AMBIGUOUS      : Payment is in a halt state
     *   - NOT_FOUND      : Session expired or not found
     *   - CANCELED       : Canceled or reversed by eSewa
     *
     * @param  string  $orderId      The transaction UUID you used when initiating payment
     * @param  float   $totalAmount  The total amount of the transaction
     *
     * @return array The full status response from eSewa
     *
     * @throws \Exception
     */
    public function checkStatus(string $orderId, float $totalAmount): array
    {
        $this->validateConfig();

        $response = Http::get($this->getV2StatusUrl(), [
            'product_code'     => $this->merchantId,
            'total_amount'     => $totalAmount,
            'transaction_uuid' => $orderId,
        ]);

        return $response->json();
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function getV1PaymentUrl(): string
    {
        return match ($this->environment) {
            'Sandbox' => self::V1_SANDBOX_PAYMENT_URL,
            'Live'    => self::V1_LIVE_PAYMENT_URL,
            default   => throw new \Exception(
                "Invalid eSewa environment '{$this->environment}'. Use 'Sandbox' or 'Live' in ESEWA_ENV."
            ),
        };
    }

    private function getV1VerifyUrl(): string
    {
        return match ($this->environment) {
            'Sandbox' => self::V1_SANDBOX_VERIFY_URL,
            'Live'    => self::V1_LIVE_VERIFY_URL,
            default   => throw new \Exception(
                "Invalid eSewa environment '{$this->environment}'. Use 'Sandbox' or 'Live' in ESEWA_ENV."
            ),
        };
    }

    private function getV2PaymentUrl(): string
    {
        return match ($this->environment) {
            'Sandbox' => self::V2_SANDBOX_PAYMENT_URL,
            'Live'    => self::V2_LIVE_PAYMENT_URL,
            default   => throw new \Exception(
                "Invalid eSewa environment '{$this->environment}'. Use 'Sandbox' or 'Live' in ESEWA_ENV."
            ),
        };
    }

    private function getV2StatusUrl(): string
    {
        return match ($this->environment) {
            'Sandbox' => self::V2_SANDBOX_STATUS_URL,
            'Live'    => self::V2_LIVE_STATUS_URL,
            default   => throw new \Exception(
                "Invalid eSewa environment '{$this->environment}'. Use 'Sandbox' or 'Live' in ESEWA_ENV."
            ),
        };
    }

    private function validateConfig(): void
    {
        if (empty($this->merchantId)) {
            throw new \Exception(
                'eSewa merchant ID (SCD) is missing. Set ESEWA_SCD in your .env file.'
            );
        }

        if (empty($this->environment)) {
            throw new \Exception(
                "eSewa environment is missing. Set ESEWA_ENV to 'Sandbox' or 'Live' in your .env file."
            );
        }
    }

    private function validateSecretKey(): void
    {
        if (empty($this->secretKey)) {
            throw new \Exception(
                'eSewa secret key is missing. Set ESEWA_SECRET_KEY in your .env file.'
            );
        }
    }
}
