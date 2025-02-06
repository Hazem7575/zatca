<?php

return [
    'live' => env('ZATCA_LIVE', false),
    'api' => [
        'base_url' => env('ZATCA_API_URL', 'https://gw-fatoora.zatca.gov.sa/e-invoicing'),
        'timeout' => env('ZATCA_API_TIMEOUT', 30),
    ]
];
