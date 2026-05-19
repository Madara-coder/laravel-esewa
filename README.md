# Laravel eSewa

A simple Laravel package to integrate eSewa payment gateway into your Laravel application.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/madara-coder/laravel-esewa.svg)](https://packagist.org/packages/madara-coder/laravel-esewa)
[![Tests](https://github.com/Madara-coder/laravel-esewa/actions/workflows/tests.yml/badge.svg)](https://github.com/Madara-coder/laravel-esewa/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## Requirements

| Package | Version |
|---------|---------|
| PHP     | ^8.1    |
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

Add these to your `.env` file:

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
    public function pay(LaravelEsewa $esewa)
    {
        $url = $esewa->esewaCheckout(
            amount: 100,
            order_id: 'ORDER-' . uniqid(),
            su: route('payment.success'),
            fu: route('payment.failure'),
        );

        return redirect($url);
    }

    public function success(Request $request, LaravelEsewa $esewa)
    {
        $verified = $esewa->verifyPayment(
            oid: $request->query('oid'),
            amt: $request->query('amt'),
            refId: $request->query('refId'),
        );

        if ($verified) {
            // Update order status in your database
            return redirect('/')->with('success', 'Payment successful!');
        }

        return redirect('/')->with('error', 'Payment verification failed.');
    }
}
```

### Option 2 — Facade

```php
use MadaraCoder\LaravelEsewa\Facades\Esewa;

// Generate checkout URL
$url = Esewa::esewaCheckout(
    amount: 500,
    order_id: 'ORDER-123',
    su: route('payment.success'),
    fu: route('payment.failure'),
    tax_amount: 10,
    service_charge: 5,
    delivery_charge: 20,
);

return redirect($url);

// Verify payment in success callback
$verified = Esewa::verifyPayment(
    oid: $request->query('oid'),
    amt: $request->query('amt'),
    refId: $request->query('refId'),
);
```

---

## Method Reference

### `esewaCheckout()`

| Parameter         | Type    | Required | Default | Description                         |
|-------------------|---------|----------|---------|-------------------------------------|
| `$amount`         | float   | ✅       | —       | Base product/service amount         |
| `$order_id`       | string  | ✅       | —       | Your unique order/transaction ID    |
| `$su`             | string  | ✅       | —       | Success callback URL                |
| `$fu`             | string  | ✅       | —       | Failure callback URL                |
| `$tax_amount`     | float   | ❌       | 0       | Tax amount                          |
| `$service_charge` | float   | ❌       | 0       | Service charge                      |
| `$delivery_charge`| float   | ❌       | 0       | Delivery charge                     |

Returns: `string` — Full eSewa redirect URL

---

### `verifyPayment()`

| Parameter | Type   | Required | Description                              |
|-----------|--------|----------|------------------------------------------|
| `$oid`    | string | ✅       | Order ID returned by eSewa in callback   |
| `$amt`    | float  | ✅       | Amount returned by eSewa in callback     |
| `$refId`  | string | ✅       | Reference ID returned by eSewa in callback |

Returns: `bool` — `true` if payment is verified, `false` otherwise

---

## eSewa Sandbox Test Credentials

Use these credentials on [https://rc.esewa.com.np](https://rc.esewa.com.np) to test:

| Field       | Value         |
|-------------|---------------|
| Merchant SCD| `EPAYTEST`    |
| eSewa ID    | `9806800001`  |
| Password    | `Nepal@123`   |
| MPIN        | `1122`        |
| OTP / Token | `123456`      |

---

## Running Tests

```bash
composer test
```

---

## Routes Example

```php
// routes/web.php
Route::get('/pay', [PaymentController::class, 'pay'])->name('payment.initiate');
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/failure', [PaymentController::class, 'failure'])->name('payment.failure');
```

---

## License

MIT — feel free to use this in any project.

---

**Made by [Debrath Sharma](https://github.com/Madara-coder)**
