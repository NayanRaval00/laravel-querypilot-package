<?php

return [
    'tables' => [
        'users' => [
            'searchable' => ['id', 'name', 'email', 'created_at'],
            'label'      => 'Registered users',
        ],
        'products' => [
            'searchable' => ['id', 'name', 'sku', 'price', 'stock', 'description', 'user_id', 'created_at', 'updated_at'],
            'label'      => 'Products',
        ],
    ],
];
