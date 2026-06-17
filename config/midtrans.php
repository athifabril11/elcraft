<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mode Midtrans (Sandbox vs Production)
    |--------------------------------------------------------------------------
    | Atur ke true HANYA setelah akun Midtrans Anda sudah terverifikasi
    | dan siap menerima pembayaran sungguhan.
    */
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | Kunci API Midtrans Sandbox (Pengembangan & Pengujian)
    |--------------------------------------------------------------------------
    | Dapatkan kunci ini dari: https://dashboard.sandbox.midtrans.com
    | Settings → Access Keys
    */
    'server_key_sandbox' => env('MIDTRANS_SERVER_KEY_SANDBOX', ''),
    'client_key_sandbox' => env('MIDTRANS_CLIENT_KEY_SANDBOX', ''),

    /*
    |--------------------------------------------------------------------------
    | Kunci API Midtrans Production (Siap Produksi)
    |--------------------------------------------------------------------------
    | Dapatkan kunci ini dari: https://dashboard.midtrans.com
    | Settings → Access Keys
    |
    | PERINGATAN: Jangan pernah commit kunci production ke repository!
    */
    'server_key_production' => env('MIDTRANS_SERVER_KEY_PRODUCTION', ''),
    'client_key_production' => env('MIDTRANS_CLIENT_KEY_PRODUCTION', ''),
];
