<?php

namespace MadaraCoder\LaravelEsewa;

use Illuminate\Support\Facades\Http;

class LaravelEsewa
{
    private string $merchant_id;
    private string $env;

    // eSewa endpoint URLs
    const SANDBOX_PAYMENT_URL = 'https://rc.esewa.com.np/epay/main?';
    const LIVE_PAYMENT_URL    = 'https://esewa.com.np/epay/main/?';
    const SANDBOX_VERIFY_URL  = 'https://rc.esewa.com.np/epay/transrec';
    const LIVE_VERIFY_URL     = 'https://esewa.com.np/epay/transrec';

    public function __construct()
    {
        $this->merchant_id = config('esewa.scd');
        $this->env = config('esewa.env');
    }

    /**
     * Generate the eSewa payment checkout URL.
     *
     * Redirect the user to this URL to initiate payment.
     *
     * @param  float   $amount           Base product/service amount (required)
     * @param  string  $order_id         Your unique order or transaction ID (required)
     * @param  string  $su               Success callback URL — eSewa redirects here on success (required)
     * @param  string  $fu               Failure callback URL — eSewa redirects here on failure (required)
     * @param  float   $tax_amount       Tax on the order (default: 0)
     * @param  float   $service_charge   Service charge (default: 0)
     * @param  float   $delivery_charge  Delivery charge (default: 0)
     * @return string                    Full eSewa redirect URL
     *
     * @throws \Exception
     */
    public function esewaCheckout(
        float $amount,
        string $order_id,
        string $su,
        string $fu,
        float $tax_amount       = 0,
        float $service_charge   = 0,
        float $delivery_charge  = 0
    ): string {
        $this->validateConfig();

        $total = $amount + $tax_amount + $service_charge + $delivery_charge;

        $params = [
            'amt'   => $amount,
            'pdc'   => $delivery_charge,
            'psc'   => $service_charge,
            'txAmt' => $tax_amount,
            'tAmt'  => $total,
            'pid'   => $order_id,
            'scd'   => $this->merchant_id,
            'su'    => $su,
            'fu'    => $fu,
        ];

        return $this->getPaymentUrl() . http_build_query($params);
    }

    /**
     * Verify a payment with eSewa after the user is redirected back.
     *
     * Call this inside your success callback route.
     * eSewa sends back: oid, amt, refId as query parameters.
     *
     * @param  string  $oid    Order ID returned by eSewa (same as pid you sent)
     * @param  float   $amt    Total amount returned by eSewa
     * @param  string  $refId  Reference ID returned by eSewa
     * @return bool            True if payment is verified successfully
     *
     * @throws \Exception
     */
    public function verifyPayment(string $oid, float $amt, string $refId): bool
    {
        $this->validateConfig();

        $response = Http::asForm()->post($this->getVerifyUrl(), [
            'amt'  => $amt,
            'scd'  => $this->merchant_id,
            'rid'  => $refId,
            'pid'  => $oid,
        ]);

        return str_contains($response->body(), 'Success');
    }

    /**
     * Get the correct payment URL based on environment.
     */
    private function getPaymentUrl(): string
    {
        return match ($this->env) {
            'Sandbox' => self::SANDBOX_PAYMENT_URL,
            'Live'    => self::LIVE_PAYMENT_URL,
            default   => throw new \Exception(
                "Invalid eSewa environment '{$this->env}'. Use 'Sandbox' or 'Live' in your .env (ESEWA_ENV)."
            ),
        };
    }

    /**
     * Get the correct verification URL based on environment.
     */
    private function getVerifyUrl(): string
    {
        return match ($this->env) {
            'Sandbox' => self::SANDBOX_VERIFY_URL,
            'Live'    => self::LIVE_VERIFY_URL,
            default   => throw new \Exception(
                "Invalid eSewa environment '{$this->env}'. Use 'Sandbox' or 'Live' in your .env (ESEWA_ENV)."
            ),
        };
    }

    /**
     * Validate required config values before any operation.
     */
    private function validateConfig(): void
    {
        if (empty($this->merchant_id)) {
            throw new \Exception(
                "eSewa merchant ID (SCD) is missing. Set ESEWA_SCD in your .env file."
            );
        }

        if (empty($this->env)) {
            throw new \Exception(
                "eSewa environment is missing. Set ESEWA_ENV to 'Sandbox' or 'Live' in your .env file."
            );
        }
    }
}
