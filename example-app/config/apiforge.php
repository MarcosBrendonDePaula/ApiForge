<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pagination Configuration
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_per_page' => env('APIFORGE_PAGINATION_DEFAULT', 15),
        'max_per_page' => env('APIFORGE_PAGINATION_MAX', 100),
        'min_per_page' => 1,
        'page_name' => 'page',
        'per_page_name' => 'per_page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filtering Configuration
    |--------------------------------------------------------------------------
    */
    'filtering' => [
        'enabled' => true,
        'case_sensitive' => false,
        'allow_empty_filters' => false,
        'max_filters_per_request' => 50,
        'default_operators' => ['eq', 'like', 'in', 'gt', 'gte', 'lt', 'lte', 'between', 'null', 'not_null'],
        'operator_aliases' => [
            '=' => 'eq',
            '!=' => 'ne',
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
            'is' => 'eq',
            'is_not' => 'ne',
            'contains' => 'like',
            'not_contains' => 'not_like',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Selection Configuration
    |--------------------------------------------------------------------------
    */
    'field_selection' => [
        'enabled' => true,
        'parameter_name' => 'fields',
        'separator' => ',',
        'relation_separator' => '.',
        'max_fields_per_request' => 50,
        'always_include_id' => true,
        'blocked_fields' => ['password', 'remember_token', 'api_token'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sorting Configuration
    |--------------------------------------------------------------------------
    */
    'sorting' => [
        'enabled' => true,
        'sort_by_parameter' => 'sort_by',
        'sort_direction_parameter' => 'sort_direction',
        'default_direction' => 'asc',
        'allowed_directions' => ['asc', 'desc'],
        'max_sorts_per_request' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    */
    'search' => [
        'enabled' => true,
        'parameter_name' => 'search',
        'min_search_length' => 2,
        'max_search_length' => 100,
        'wildcard_character' => '*',
        'search_operator' => 'like',
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('APIFORGE_CACHE_ENABLED', true),
        'driver' => env('CACHE_DRIVER', 'file'),
        'default_ttl' => env('APIFORGE_CACHE_TTL', 3600), // 1 hour
        'key_prefix' => 'apiforge_',
        'tags_enabled' => false,
        'cache_null_results' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'sql_injection_protection' => true,
        'sanitize_input' => true,
        'max_query_execution_time' => 30, // seconds
        'rate_limiting' => [
            'enabled' => false,
            'max_requests_per_minute' => 60,
        ],
        'allowed_request_methods' => ['GET'],
        'validate_filter_permissions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug and Logging
    |--------------------------------------------------------------------------
    */
    'debug' => [
        'enabled' => env('APIFORGE_DEBUG_ENABLED', env('APP_DEBUG', false)),
        'log_queries' => env('APIFORGE_LOG_QUERIES', false),
        'log_slow_queries' => true,
        'slow_query_threshold' => 1000, // milliseconds
        'include_sql_in_response' => false,
        'log_invalid_filters' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Configuration
    |--------------------------------------------------------------------------
    */
    'response' => [
        'include_metadata' => true,
        'include_filter_info' => true,
        'include_pagination_urls' => true,
        'include_query_stats' => env('APIFORGE_DEBUG_ENABLED', false),
        'wrap_response' => true,
        'success_key' => 'success',
        'data_key' => 'data',
        'meta_key' => 'meta',
        'errors_key' => 'errors',
    ],

    /*
    |--------------------------------------------------------------------------
    | Type Casting Configuration
    |--------------------------------------------------------------------------
    */
    'type_casting' => [
        'enabled' => true,
        'strict_mode' => false,
        'cast_empty_strings_to_null' => true,
        'boolean_true_values' => ['true', '1', 'yes', 'on'],
        'boolean_false_values' => ['false', '0', 'no', 'off'],
        'date_formats' => [
            'Y-m-d',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:s\Z',
            'Y-m-d\TH:i:sP',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'parameter_validation' => true,
        'sanitize_parameters' => true,
        'normalize_parameters' => true,
        'validate_field_access' => true,
        'auto_detect_filters' => true,
        'ignore_unknown_parameters' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Example App Specific Configuration
    |--------------------------------------------------------------------------
    */
    'example_app' => [
        'demo_mode' => env('DEMO_MODE', true),
        'seed_data' => env('SEED_DEMO_DATA', true),
        'show_sql_queries' => env('SHOW_SQL_QUERIES', false),
        'enable_query_log' => env('ENABLE_QUERY_LOG', false),
        'max_demo_results' => 1000,
    ],
];