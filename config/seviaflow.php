<?php

declare(strict_types=1);

return [
    'paths' => [
        'resources' => app_path('SeviaFlow/Resources'),
    ],
    'namespaces' => [
        'resources' => 'App\\SeviaFlow\\Resources',
    ],
    'resources' => [],
    'performance' => [
        'select_only_visible_columns' => true,
        'pagination' => [
            'default_per_page' => 15,
            'max_per_page' => 100,
            'per_page_options' => [15, 25, 50],
            'mode' => 'length_aware',
        ],
        'search' => [
            'default_mode' => 'starts_with',
            'mode' => 'starts_with',
        ],
        'cache' => [
            'enabled' => env('SEVIAFLOW_CACHE_ENABLED', false),
            'store' => null,
            'ttl' => 3600,
            'resources' => true,
        ],
    ],
    'permissions' => [
        'enabled' => false,
        'user_model' => null,
        'role_management' => [
            'resource' => '__settings.roles',
            'ability' => 'update',
        ],
        'role_assignments' => [
            'resource' => '__settings.admin-users',
            'ability' => 'update',
        ],
    ],
    'forms' => [
        'default_currency' => 'USD',
    ],
    'analytics' => [
        'default_limit' => 20,
        'max_limit' => 500,
    ],
    'workflows' => [
        'enabled' => true,
    ],
    'export' => [
        'max_rows' => 5000,
    ],
    'import' => [
        'max_rows' => 1000,
        'preview_rows' => 20,
    ],
];
