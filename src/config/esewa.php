<?php

return [

    /*
    |--------------------------------------------------------------------------
    | eSewa Merchant Code (SCD)
    |--------------------------------------------------------------------------
    |
    | Your merchant code provided by eSewa.
    | For sandbox testing use the default: EPAYTEST
    | For production replace with your actual merchant code.
    |
    */
    'scd' => env('ESEWA_SCD', 'EPAYTEST'),

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
