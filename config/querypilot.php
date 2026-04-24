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
            'searchable' => ['id', 'name', 'sku', 'price', 'stock', 'description', 'user_id', 'created_at', 'updated_at'],
            'label'      => 'Products',
        ],
        'posts' => [
            'searchable' => ['id', 'title', 'body', 'user_id', 'created_at', 'updated_at'],
            'label'      => 'Posts',
        ],
        'comments' => [
            'searchable' => ['id', 'post_id', 'user_id', 'comment', 'created_at', 'updated_at'],
            'label'      => 'Comments',
        ],
        'profiles' => [
            'searchable' => ['id', 'user_id', 'bio', 'avatar_url', 'created_at'],
            'label'      => 'Profiles catalog',
        ],
    ],

    'relationships' => [
        'users hasOne profiles via profiles.user_id = users.id',
        'users hasMany posts via posts.user_id = users.id',
        'users hasMany products via products.user_id = users.id',
        'users hasMany comments via comments.user_id = users.id',
    ],
];
