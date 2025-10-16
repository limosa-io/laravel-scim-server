<?php

return [
    "publish_routes" => true,
    'omit_main_schema_in_return' => false,
    'omit_null_values' => true,

    'path' => env('SCIM_BASE_PATH', '/scim'),
    'domain' => env('SCIM_DOMAIN', null),
    'middleware' => env('SCIM_MIDDLEWARE', []),
    'public_middleware' => env('SCIM_PUBLIC_MIDDLEWARE', []),

    'pagination' => [
        'defaultPageSize' => 10,
        'maxPageSize' => 100,
        'cursorPaginationEnabled' => true,
    ]
];
