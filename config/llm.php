<?php

return [
    'provider' => env('LLM_PROVIDER', 'deepseek'),

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),
    ],

    'timeout' => (int) env('LLM_TIMEOUT', 30),
];
