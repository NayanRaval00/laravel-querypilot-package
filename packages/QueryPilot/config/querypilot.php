<?php

return [

    'provider' => env('AGENTIS_PROVIDER', 'gemini'),
    'max_rows' => 100,
    'cache_ttl' => 60,

    'tables' => [
        'users' => [
            'searchable' => ['id', 'name', 'email', 'created_at'],
            'label'      => 'Registered users',
        ],
        'products' => [
            'searchable' => ['id', 'name', 'sku', 'price', 'stock', 'description', 'user_id', 'created_at'],
            'label'      => 'Products catalog',
        ]
    ],

    'relationships' => [
        'users hasMany products via products.user_id = users.id',
    ],
];
