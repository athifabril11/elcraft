<?php

return [
    'api_key'  => env('RAJAONGKIR_API_KEY'),
    'base_url' => env('RAJAONGKIR_BASE_URL', 'https://api.rajaongkir.com/starter'),
    'origin'   => env('RAJAONGKIR_ORIGIN_CITY_ID', '256'), // Default: Jember (256)

    /*
    |--------------------------------------------------------------------------
    | Supported Couriers
    |--------------------------------------------------------------------------
    |
    | Single source of truth for the courier whitelist.
    | Key   = courier code passed to the Komerce API (POST /calculate/domestic-cost)
    | Value = display label shown to customers on the checkout page
    |
    | This array is referenced by:
    |   - app/Services/ShippingService.php  (guard / fallback)
    |   - app/Http/Controllers/ShippingController.php  (request validation)
    |   - app/Http/Controllers/CheckoutController.php  (request validation)
    |   - resources/views/checkout/index.blade.php     (courier buttons)
    |
    */
    'couriers' => [
        'jne'  => 'JNE',
        'jnt'  => 'J&T Express',
        'pos'  => 'POS Indonesia',
        'tiki' => 'TIKI',
        'lion' => 'Lion Parcel',
        'sap'  => 'SAP Express',
    ],
];
