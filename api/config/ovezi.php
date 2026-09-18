<?php

return [
    'deep_link_scheme' => env('APP_DEEP_LINK_SCHEME', 'ovezi'),
    'social' => [
        'google_client_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_CLIENT_IDS', '')),
        ))),
        'apple_client_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('APPLE_CLIENT_IDS', '')),
        ))),
    ],
];
