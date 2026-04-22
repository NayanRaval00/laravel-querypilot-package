<?php

return [

    'provider' => env('AGENTIS_PROVIDER', 'gemini'),

    'tables' => [
        'users' => [
            'searchable' => ['id', 'name', 'email', 'created_at'],
            'label'      => 'Registered users',
        ],
    ],

];
