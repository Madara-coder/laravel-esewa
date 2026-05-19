# Laravel eSewa

A Laravel package to integrate eSewa payment gateway into your Laravel application.
Supports both **V1 (Legacy)** and **V2 (HMAC-SHA256)** APIs.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/madara-coder/laravel-esewa.svg)](https://packagist.org/packages/madara-coder/laravel-esewa)
[![Tests](https://github.com/Madara-coder/laravel-esewa/actions/workflows/tests.yml/badge.svg)](https://github.com/Madara-coder/laravel-esewa/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## Requirements

| Package | Version    |
|---------|------------|
| PHP     | ^8.1       |
| Laravel | 10, 11, 12 |

---

## Installation

```bash
composer require madara-coder/laravel-esewa
```

### Publish the config file

```bash
php artisan vendor:publish --tag=esewa-config
```

This creates `config/esewa.php` in your application.

---

## Configuration

Add these values to your `.env` file:

```env
# Sandbox (for testing)
ESEWA_SCD=EPAYTEST
ESEWA_SECRET_KEY=8gBm/:&EnhH.1/q
ESEWA_ENV=Sandbox

# Live (for production)
# ESEWA_SCD=your_actual_merchant_code
# ESEWA_SECRET_KEY=your_actual_secret_key
# ESEWA_ENV=Live
```

> **Note:** `ESEWA_SECRET_KEY` is only required for V2. If you are using V1 only, you can skip it.

---

## Which version should I use?

| | V1 (Legacy) | V2 (Recommended) |
|---|---|---|
| Secret key needed | ❌ No | ✅ Yes |
| Security | Basic | HMAC-SHA256 signature |
| Payment method | Redirect URL | HTML form POST |
| Verify callback | POST to eSewa | Decode Base64 + verify signature |
| Check status anytime | ❌ No | ✅ Yes |
| Still works | ✅ Yes | ✅ Yes |

**Use V2 for all new projects.** V1 still works and is fully supported by this package,
but V2 is eSewa's recommended approach and is significantly more secure.

---

## V1 Usage (Legacy API)

V1 works by building a URL with your order details and redirecting the user to eSewa.
No secret key is required.

### Option 1 — Dependency Injection

```php
use MadaraCoder\LaravelEsewa\LaravelEsewa;

class PaymentController extends Controller
{
    // Step 1: Redirect user to eSewa
    public function pay(LaravelEsewa $esewa): \Illuminate\Http\RedirectResponse
    {
        $url = $esewa->esewaCheckout(
            amount: 500,
            orderId: 'ORDER-' . uniqid(),
            successUrl: route('payment.success'),
            failureUrl: route('payment.failure'),
            taxAmount: 10,
            serviceCharge: 5,
            deliveryCharge: 20,
        );

        return redirect($url);
    }

    // Step 2: Verify payment inside your success callback
    // eSewa sends back: ?oid=...&amt=...&refId=...
    public function success(Request $request, LaravelEsewa $esewa): \Illuminate\Http\RedirectResponse
    {
        $verified = $esewa->verifyPayment(
            orderId: $request->query('oid'),
            amount: $request->query('amt'),
            referenceId: $request->query('refId'),
        );

        if ($verified) {
            // Mark the order as paid in your database
            return redirect('/')->with('success', 'Payment successful!');
        }

        return redirect('/')->with('error', 'Payment verification failed.');
    }

    public function failure(): \Illuminate\Http\RedirectResponse
    {
        return redirect('/')->with('error', 'Payment was cancelled or failed.');
    }
}
```

### Option 2 — Facade

```php
use MadaraCoder\LaravelEsewa\Facades\Esewa;

// Step 1: Generate the checkout URL and redirect the user
$url = Esewa::esewaCheckout(
    amount: 500,
    orderId: 'ORDER-' . uniqid(),
    successUrl: route('payment.success'),
    failureUrl: route('payment.failure'),
    taxAmount: 10,
    serviceCharge: 5,
    deliveryCharge: 20,
);

return redirect($url);

// Step 2: Verify payment inside your success callback
// eSewa sends back: ?oid=...&amt=...&refId=...
$verified = Esewa::verifyPayment(
    orderId: $request->query('oid'),
    amount: $request->query('amt'),
    referenceId: $request->query('refId'),
);
```

---

## V2 Usage (Recommended — HMAC-SHA256)

V2 is more secure. Your backend generates a cryptographic signature, your Blade view submits
it as an HTML form to eSewa, and eSewa returns a Base64 encoded response after payment.

### Option 1 — Dependency Injection

```php
use MadaraCoder\LaravelEsewa\LaravelEsewa;

class PaymentController extends Controller
{
    // Step 1: Generate signed form data and pass to your Blade view
    public function pay(LaravelEsewa $esewa): \Illuminate\View\View
    {
        $formData = $esewa->generateFormData(
            amount: 500,
            orderId: 'ORDER-' . uniqid(),
            successUrl: route('payment.v2.success'),
            failureUrl: route('payment.v2.failure'),
            taxAmount: 10,
            serviceCharge: 5,
            deliveryCharge: 20,
        );

        // Pass $formData to your Blade view
        return view('payment', compact('formData'));
    }

    // Step 3: Verify the Base64 encoded response eSewa sends back
    // eSewa sends back: ?data=<Base64EncodedResponse>
    public function success(Request $request, LaravelEsewa $esewa): \Illuminate\Http\RedirectResponse
    {
        $verified = $esewa->verifyV2Payment($request->query('data'));

        if ($verified) {
            // Mark the order as paid in your database
            return redirect('/')->with('success', 'Payment successful!');
        }

        return redirect('/')->with('error', 'Payment verification failed.');
    }

    // Step 4 (Optional): Check a transaction status at any time
    public function checkPaymentStatus(LaravelEsewa $esewa): array
    {
        return $esewa->checkStatus(
            orderId: 'ORDER-001',
            totalAmount: 535,
        );
    }

    public function failure(): \Illuminate\Http\RedirectResponse
    {
        return redirect('/')->with('error', 'Payment was cancelled or failed.');
    }
}
```

### Option 2 — Facade

```php
use MadaraCoder\LaravelEsewa\Facades\Esewa;

// Step 1: Generate signed form data
$formData = Esewa::generateFormData(
    amount: 500,
    orderId: 'ORDER-' . uniqid(),
    successUrl: route('payment.v2.success'),
    failureUrl: route('payment.v2.failure'),
    taxAmount: 10,
    serviceCharge: 5,
    deliveryCharge: 20,
);

return view('payment', compact('formData'));

// Step 3: Verify the callback
$verified = Esewa::verifyV2Payment($request->query('data'));

// Step 4 (Optional): Check status anytime
$status = Esewa::checkStatus(orderId: 'ORDER-001', totalAmount: 535);
```

### Step 2 — Blade Form (frontend)

Your Blade view submits the signed form data as a POST form to eSewa's payment page.
`$formData['payment_url']` automatically points to sandbox or live depending on your `.env`.

```html
<form action="{{ $formData['payment_url'] }}" method="POST">
    <input type="hidden" name="amount"                  value="{{ $formData['amount'] }}">
    <input type="hidden" name="tax_amount"              value="{{ $formData['tax_amount'] }}">
    <input type="hidden" name="total_amount"            value="{{ $formData['total_amount'] }}">
    <input type="hidden" name="transaction_uuid"        value="{{ $formData['transaction_uuid'] }}">
    <input type="hidden" name="product_code"            value="{{ $formData['product_code'] }}">
    <input type="hidden" name="product_service_charge"  value="{{ $formData['product_service_charge'] }}">
    <input type="hidden" name="product_delivery_charge" value="{{ $formData['product_delivery_charge'] }}">
    <input type="hidden" name="success_url"             value="{{ $formData['success_url'] }}">
    <input type="hidden" name="failure_url"             value="{{ $formData['failure_url'] }}">
    <input type="hidden" name="signed_field_names"      value="{{ $formData['signed_field_names'] }}">
    <input type="hidden" name="signature"               value="{{ $formData['signature'] }}">
    <button type="submit">Pay with eSewa</button>
</form>
```

---

## Routes

```php
// routes/web.php

// V1 routes
Route::get('/pay', [PaymentController::class, 'pay'])->name('payment.initiate');
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/failure', [PaymentController::class, 'failure'])->name('payment.failure');

// V2 routes (success URL receives ?data=<Base64> from eSewa)
Route::get('/v2/pay', [PaymentController::class, 'pay'])->name('payment.v2.initiate');
Route::get('/v2/payment/success', [PaymentController::class, 'success'])->name('payment.v2.success');
Route::get('/v2/payment/failure', [PaymentController::class, 'failure'])->name('payment.v2.failure');
```

---

## Method Reference

### V1 Methods

#### `esewaCheckout()`

| Parameter         | Type   | Required | Default | Description                    |
|-------------------|--------|----------|---------|--------------------------------|
| `$amount`         | float  | ✅       | —       | Base product or service amount |
| `$orderId`        | string | ✅       | —       | Your unique order ID           |
| `$successUrl`     | string | ✅       | —       | Redirect URL on success        |
| `$failureUrl`     | string | ✅       | —       | Redirect URL on failure        |
| `$taxAmount`      | float  | ❌       | 0       | Tax on the order               |
| `$serviceCharge`  | float  | ❌       | 0       | Service charge                 |
| `$deliveryCharge` | float  | ❌       | 0       | Delivery charge                |

Returns: `string` — Full eSewa V1 redirect URL

---

#### `verifyPayment()`

| Parameter      | Type   | Required | Description                                |
|----------------|--------|----------|--------------------------------------------|
| `$orderId`     | string | ✅       | Order ID returned by eSewa in the callback |
| `$amount`      | float  | ✅       | Amount returned by eSewa in the callback   |
| `$referenceId` | string | ✅       | Reference ID returned by eSewa             |

Returns: `bool` — `true` if payment verified, `false` otherwise

---

### V2 Methods

#### `generateFormData()`

| Parameter         | Type   | Required | Default | Description                    |
|-------------------|--------|----------|---------|--------------------------------|
| `$amount`         | float  | ✅       | —       | Base product or service amount |
| `$orderId`        | string | ✅       | —       | Your unique order ID           |
| `$successUrl`     | string | ✅       | —       | Redirect URL on success        |
| `$failureUrl`     | string | ✅       | —       | Redirect URL on failure        |
| `$taxAmount`      | float  | ❌       | 0       | Tax on the order               |
| `$serviceCharge`  | float  | ❌       | 0       | Service charge                 |
| `$deliveryCharge` | float  | ❌       | 0       | Delivery charge                |

Returns: `array` — All form fields including `signature` and `payment_url`

---

#### `verifyV2Payment()`

| Parameter      | Type   | Required | Description                                          |
|----------------|--------|----------|------------------------------------------------------|
| `$encodedData` | string | ✅       | The raw Base64 string from eSewa's callback `?data=` |

Returns: `bool` — `true` if signature is valid and status is `COMPLETE`

---

#### `checkStatus()`

| Parameter      | Type   | Required | Description                           |
|----------------|--------|----------|---------------------------------------|
| `$orderId`     | string | ✅       | The transaction UUID used when paying |
| `$totalAmount` | float  | ✅       | The total amount of the transaction   |

Returns: `array` — Full status response from eSewa

Possible values for `status` in the response:

| Status           | Meaning                                  |
|------------------|------------------------------------------|
| `COMPLETE`       | Payment was successful                   |
| `PENDING`        | Payment initiated but not completed yet  |
| `FULL_REFUND`    | Full payment refunded to the customer    |
| `PARTIAL_REFUND` | Partial payment refunded                 |
| `AMBIGUOUS`      | Payment is in a halt or uncertain state  |
| `NOT_FOUND`      | Session expired or transaction not found |
| `CANCELED`       | Canceled or reversed from eSewa's side   |

---

## Sandbox Test Credentials

Use these on [https://rc.esewa.com.np](https://rc.esewa.com.np) for V1 testing
and [https://rc-epay.esewa.com.np](https://rc-epay.esewa.com.np) for V2 testing:

| Field        | Value             |
|--------------|-------------------|
| Merchant SCD | `EPAYTEST`        |
| Secret Key   | `8gBm/:&EnhH.1/q` |
| eSewa ID     | `9806800001`      |
| Password     | `Nepal@123`       |
| MPIN         | `1122`            |
| OTP / Token  | `123456`          |

---

## Running Tests

```bash
composer test
```

---

## License

MIT — free to use in any project.

---

**Made by [Debrath Sharma](https://github.com/Madara-coder)**
