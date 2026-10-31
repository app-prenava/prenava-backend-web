<?php

return [
    'ttl_seconds' => [
        'ibu_hamil' => env('AUTH_TTL_IBU_HAMIL_SECONDS', 30 * 24 * 60 * 60), // 30 hari untuk mobile
        'umum'      => env('AUTH_TTL_UMUM_SECONDS', 30 * 24 * 60 * 60),      // 30 hari
        'bidan'     => env('AUTH_TTL_BIDAN_SECONDS', 12 * 60 * 60),          // 12 jam
        'dinkes'    => env('AUTH_TTL_DINKES_SECONDS', 12 * 60 * 60),         // 12 jam
        'default'   => env('AUTH_TTL_DEFAULT_SECONDS', 30 * 24 * 60 * 60),   // Default 30 hari
    ],
];
