<?php

declare(strict_types=1);

namespace MadaraCoder\LaravelEsewa;

use Illuminate\Support\Facades\Http;

class LaravelEsewa
{
    private string $merchantId;

    private string $environment;

    private const SANDBOX_PAYMENT_URL = 'https://rc.esewa.com.np/epay/main?';

    private const LIVE_PAYMENT_URL = 'https://esewa.com.np/epay/main/?';

    private const SANDBOX_VERIFY_URL = 'https://rc.esewa.com.np/epay/transrec';

    private const LIVE_VERIFY_URL = 'https://esewa.com.np/epay/transrec';

    public function __construct()
    {
        $this->merchantId = config('esewa.scd');
        $this->environment = config('esewa.env');
    }

    /**
     * Generate the eSewa payment checkout URL.
     *
     * Redirect the user to this URL to initiate payment on eSewa.
     *
     * @param  float   $amount          Base product or service amount (required)
     * @param  string  $orderId         Your unique order or transaction ID (required)
     * @param  string  $successUrl      URL eSewa redirects to on successful payment (required)
     * @param  string  $failureUrl      URL eSewa redirects to on failed payment (required)
     * @param  float   $taxAmount       Tax applied on the order (default: 0)
     * @param  float   $serviceCharge   Service charge applied on the order (default: 0)
     * @param  float   $deliveryCharge  Delivery charge applied on the order (default: 0)
     *
     * @return string Full eSewa redirect URL
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

        return $this->getPaymentUrl() . http_build_query($params);
    }

    /**
     * Verify a payment with eSewa after the user is redirected back.
     *
     * Call this inside your success callback route.
     * eSewa returns: oid, amt, refId as query parameters in the callback.
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

        $response = Http::asForm()->post($this->getVerifyUrl(), [
            'amt' => $amount,
            'scd' => $this->merchantId,
            'rid' => $referenceId,
            'pid' => $orderId,
        ]);

        return str_contains($response->body(), 'Success');
    }

    /**
     * Get the correct payment URL based on the configured environment.
     *
     * @throws \Exception
     */
    private function getPaymentUrl(): string
    {
        return match ($this->environment) {
            'Sandbox' => self::SANDBOX_PAYMENT_URL,
            'Live'    => self::LIVE_PAYMENT_URL,
            default   => throw new \Exception(
                "Invalid eSewa environment '{$this->environment}'. Use 'Sandbox' or 'Live' in ESEWA_ENV."
            ),
        };
    }

    /**
     * Get the correct verification URL based on the configured environment.
     *
     * @throws \Exception
     */
    private function getVerifyUrl(): string
    {
        return match ($this->environment) {
            'Sandbox' => self::SANDBOX_VERIFY_URL,
            'Live'    => self::LIVE_VERIFY_URL,
            default   => throw new \Exception(
                "Invalid eSewa environment '{$this->environment}'. Use 'Sandbox' or 'Live' in ESEWA_ENV."
            ),
        };
    }

    /**
     * Validate required config values before performing any operation.
     *
     * @throws \Exception
     */
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
}
