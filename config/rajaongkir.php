<?php

return [
    'api_key'  => env('RAJAONGKIR_API_KEY'),
    'base_url' => env('RAJAONGKIR_BASE_URL', 'https://api.rajaongkir.com/starter'),
    'origin'   => env('RAJAONGKIR_ORIGIN_CITY_ID', '152'), // Default: Jakarta Selatan (152)
];
