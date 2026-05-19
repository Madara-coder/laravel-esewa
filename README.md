# Laravel eSewa

A simple Laravel package to integrate eSewa payment gateway into your Laravel application.

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
ESEWA_ENV=Sandbox

# Live (for production)
# ESEWA_SCD=your_actual_merchant_code
# ESEWA_ENV=Live
```

---

## Usage

### Option 1 — Dependency Injection

```php
use MadaraCoder\LaravelEsewa\LaravelEsewa;

class PaymentController extends Controller
{
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

// Generate the checkout URL and redirect
$url = Esewa::esewaCheckout(
    amount: 500,
    orderId: 'ORDER-123',
    successUrl: route('payment.success'),
    failureUrl: route('payment.failure'),
    taxAmount: 10,
    serviceCharge: 5,
    deliveryCharge: 20,
);

return redirect($url);

// Verify the payment in your success callback
$verified = Esewa::verifyPayment(
    orderId: $request->query('oid'),
    amount: $request->query('amt'),
    referenceId: $request->query('refId'),
);
```

---

## Routes

```php
// routes/web.php
Route::get('/pay', [PaymentController::class, 'pay'])->name('payment.initiate');
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/failure', [PaymentController::class, 'failure'])->name('payment.failure');
```

---

## Method Reference

### `esewaCheckout()`

| Parameter         | Type   | Required | Default | Description                      |
|-------------------|--------|----------|---------|----------------------------------|
| `$amount`         | float  | ✅       | —       | Base product or service amount   |
| `$orderId`        | string | ✅       | —       | Your unique order ID             |
| `$successUrl`     | string | ✅       | —       | Redirect URL on success          |
| `$failureUrl`     | string | ✅       | —       | Redirect URL on failure          |
| `$taxAmount`      | float  | ❌       | 0       | Tax on the order                 |
| `$serviceCharge`  | float  | ❌       | 0       | Service charge                   |
| `$deliveryCharge` | float  | ❌       | 0       | Delivery charge                  |

Returns: `string` — Full eSewa redirect URL

---

### `verifyPayment()`

| Parameter      | Type   | Required | Description                                  |
|----------------|--------|----------|----------------------------------------------|
| `$orderId`     | string | ✅       | Order ID returned by eSewa in the callback   |
| `$amount`      | float  | ✅       | Amount returned by eSewa in the callback     |
| `$referenceId` | string | ✅       | Reference ID returned by eSewa in callback   |

Returns: `bool` — `true` if payment verified, `false` otherwise

---

## Sandbox Test Credentials

Use these credentials on [https://rc.esewa.com.np](https://rc.esewa.com.np) for testing:

| Field        | Value        |
|--------------|--------------|
| Merchant SCD | `EPAYTEST`   |
| eSewa ID     | `9806800001` |
| Password     | `Nepal@123`  |
| MPIN         | `1122`       |
| OTP / Token  | `123456`     |

---

## Running Tests

```bash
composer test

## License

MIT — feel free to use this in any project.

---

**Made by [Debrath Sharma](https://github.com/Madara-coder)**
