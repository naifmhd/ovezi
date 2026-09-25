<?php

return [
    'deep_link_scheme' => env('APP_DEEP_LINK_SCHEME', 'ovezi'),
    'support_email' => env('OVEZI_SUPPORT_EMAIL', 'naifmhd@gmail.com'),
    'app_store_url' => env('OVEZI_APP_STORE_URL'),
    'play_store_url' => env('OVEZI_PLAY_STORE_URL'),
    'android_package' => env('OVEZI_ANDROID_PACKAGE', 'com.ovezi.app'),
    'android_sha256_fingerprints' => array_values(array_filter(array_map('trim', explode(',', (string) env('ANDROID_SHA256_FINGERPRINTS', ''))))),
    'social' => [
        'apple_client_id' => env('APPLE_CLIENT_ID', 'com.ovezi.app'),
        'apple_team_id' => env('APPLE_TEAM_ID'),
        'apple_key_id' => env('APPLE_KEY_ID'),
        'apple_private_key' => env('APPLE_PRIVATE_KEY'),
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
