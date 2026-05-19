<?php

return [

    /*
    |--------------------------------------------------------------------------
    | eSewa Merchant Code (SCD / Product Code)
    |--------------------------------------------------------------------------
    |
    | Your merchant code provided by eSewa.
    | Used in both V1 and V2.
    | Sandbox default: EPAYTEST
    |
    */
    'scd' => env('ESEWA_SCD', 'EPAYTEST'),

    /*
    |--------------------------------------------------------------------------
    | eSewa Secret Key (V2 only)
    |--------------------------------------------------------------------------
    |
    | Your secret key provided by eSewa for HMAC-SHA256 signature generation.
    | Only required for V2 payments.
    | Sandbox default: 8gBm/:&EnhH.1/q
    |
    */
    'secret_key' => env('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Determines which eSewa endpoint to use.
    |
    | Options:
    |   "Sandbox"  - For development and testing
    |   "Live"     - For production
    |
    */
    'env' => env('ESEWA_ENV', 'Sandbox'),

];
