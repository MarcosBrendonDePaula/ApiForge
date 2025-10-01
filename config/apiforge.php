<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Pagination Settings
    |--------------------------------------------------------------------------
    |
    | These settings control the default pagination behavior for API endpoints
    | that use the advanced filtering system.
    |
    */
    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 100,
        'min_per_page' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Selection Settings
    |--------------------------------------------------------------------------
    |
    | Configure field selection behavior including limits and validation.
    |
    */
    'field_selection' => [
        'enabled' => true,
        'max_fields' => 50,
        'required_fields' => ['id'],
        'blocked_fields' => ['password', 'remember_token', 'api_token'],
        'allow_relationships' => true,
        'max_relationship_depth' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filter Settings
    |--------------------------------------------------------------------------
    |
    | Configure filtering behavior and available operators.
    |
    */
    'filters' => [
        'enabled' => true,
        'case_sensitive' => false,
        'trim_values' => true,
        'max_filters' => 20,
        
        'available_operators' => [
            'eq' => ['name' => 'Equals', 'description' => 'Exact match'],
            'ne' => ['name' => 'Not Equals', 'description' => 'Not equal to'],
            'like' => ['name' => 'Like', 'description' => 'Contains (use * as wildcard)'],
            'not_like' => ['name' => 'Not Like', 'description' => 'Does not contain'],
            'gt' => ['name' => 'Greater Than', 'description' => 'Greater than'],
            'gte' => ['name' => 'Greater Than or Equal', 'description' => 'Greater than or equal to'],
            'lt' => ['name' => 'Less Than', 'description' => 'Less than'],
            'lte' => ['name' => 'Less Than or Equal', 'description' => 'Less than or equal to'],
            'in' => ['name' => 'In Array', 'description' => 'Value in list (comma separated)'],
            'not_in' => ['name' => 'Not In Array', 'description' => 'Value not in list'],
            'between' => ['name' => 'Between', 'description' => 'Between two values (pipe separated)'],
            'not_between' => ['name' => 'Not Between', 'description' => 'Not between two values'],
            'null' => ['name' => 'Is Null', 'description' => 'Field is null'],
            'not_null' => ['name' => 'Is Not Null', 'description' => 'Field is not null'],
            'starts_with' => ['name' => 'Starts With', 'description' => 'Starts with value'],
            'ends_with' => ['name' => 'Ends With', 'description' => 'Ends with value'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for API responses.
    |
    */
    'cache' => [
        'enabled' => false,
        'default_ttl' => 3600, // 1 hour
        'key_prefix' => 'api_filters_',
        'tags' => ['api', 'filters'],
        'store' => null, // Use default cache store
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security configuration for filtering and validation.
    |
    */
    'security' => [
        'sanitize_input' => true,
        'strip_tags' => true,
        'max_query_length' => 2000,
        'blocked_keywords' => [
            'select', 'insert', 'update', 'delete', 'drop', 'create', 'alter',
            'union', 'script', 'javascript', 'eval', 'exec'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Settings
    |--------------------------------------------------------------------------
    |
    | Configure API response format and metadata.
    |
    */
    'response' => [
        'include_metadata' => true,
        'include_filter_info' => true,
        'include_pagination_info' => true,
        'timestamp_format' => 'c', // ISO 8601
        'timezone' => 'UTC',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Settings
    |--------------------------------------------------------------------------
    |
    | Configure validation rules and error handling.
    |
    */
    'validation' => [
        'strict_mode' => false,
        'ignore_invalid_filters' => true,
        'validate_field_existence' => true,
        'validate_relationship_existence' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Settings
    |--------------------------------------------------------------------------
    |
    | Enable debug features for development.
    |
    */
    'debug' => [
        'enabled' => env('APP_DEBUG', false),
        'log_queries' => false,
        'log_filters' => false,
        'include_query_time' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for AI-powered documentation generation.
    |
    */
    'documentation' => [
        'enabled' => true,
        
        'cache' => [
            'enabled' => true,
            'ttl' => 3600, // 1 hour
            'key_prefix' => 'apiforge_docs_',
        ],
        
        'llm' => [
            'priority' => ['claude', 'openai', 'deepseek'], // Order of preference
            
            'providers' => [
                'openai' => [
                    'enabled' => env('APIFORGE_OPENAI_ENABLED', false),
                    'api_key' => env('OPENAI_API_KEY'),
                    'endpoint' => 'https://api.openai.com/v1/chat/completions',
                    'model' => env('APIFORGE_OPENAI_MODEL', 'gpt-4o'),
                    'temperature' => 0.1,
                    'max_tokens' => 4000,
                    'timeout' => 60,
                ],
                
                'claude' => [
                    'enabled' => env('APIFORGE_CLAUDE_ENABLED', false),
                    'api_key' => env('CLAUDE_API_KEY'),
                    'endpoint' => 'https://api.anthropic.com/v1/messages',
                    'model' => env('APIFORGE_CLAUDE_MODEL', 'claude-3-sonnet-20240229'),
                    'version' => '2023-06-01',
                    'temperature' => 0.1,
                    'max_tokens' => 4000,
                    'timeout' => 60,
                ],
                
                'deepseek' => [
                    'enabled' => env('APIFORGE_DEEPSEEK_ENABLED', false),
                    'api_key' => env('DEEPSEEK_API_KEY'),
                    'endpoint' => 'https://api.deepseek.com/chat/completions',
                    'model' => env('APIFORGE_DEEPSEEK_MODEL', 'deepseek-chat'),
                    'temperature' => 0.1,
                    'max_tokens' => 4000,
                    'timeout' => 60,
                ],
            ],
        ],
        
        'output' => [
            'default_path' => storage_path('app/docs'),
            'formats' => ['json', 'yaml', 'html'],
            'default_format' => 'json',
        ],
        
        'templates' => [
            'openapi_version' => '3.0.0',
            'info' => [
                'contact' => [
                    'name' => env('API_CONTACT_NAME', 'API Support'),
                    'email' => env('API_CONTACT_EMAIL'),
                    'url' => env('API_CONTACT_URL'),
                ],
                'license' => [
                    'name' => env('API_LICENSE_NAME', 'MIT'),
                    'url' => env('API_LICENSE_URL'),
                ],
            ],
            'servers' => [
                [
                    'url' => env('APP_URL', 'http://localhost') . '/api',
                    'description' => 'Main API Server',
                ],
            ],
        ],
        
        'enhancement' => [
            'include_examples' => true,
            'include_error_responses' => true,
            'detailed_descriptions' => true,
            'parameter_validation' => true,
            'response_schemas' => true,
            'security_definitions' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for performance optimization features.
    |
    */
    'performance' => [
        'query_optimization' => env('APIFORGE_QUERY_OPTIMIZATION', true),
        'optimize_pagination' => env('APIFORGE_OPTIMIZE_PAGINATION', true),
        'eager_loading' => env('APIFORGE_EAGER_LOADING', true),
        'n_plus_one_detection' => env('APIFORGE_N_PLUS_ONE_DETECTION', false),
        
        'query_limits' => [
            'max_joins' => 5,
            'max_where_conditions' => 20,
            'max_relationships_depth' => 3,
            'cursor_pagination_threshold' => 100, // pages
        ],
        
        'indexing_hints' => [
            'suggest_indexes' => env('APIFORGE_SUGGEST_INDEXES', false),
            'log_slow_queries' => env('APIFORGE_LOG_SLOW_QUERIES', true),
            'slow_query_threshold' => 100, // ms
        ],
        
        'memory_management' => [
            'chunk_size' => 1000,
            'max_memory_usage' => '256M',
            'gc_probability' => 10, // percentage
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Virtual Fields Settings
    |--------------------------------------------------------------------------
    |
    | Configure virtual field behavior including caching, performance limits,
    | and sorting constraints.
    |
    */
    'virtual_fields' => [
        'enabled' => true,
        'cache_enabled' => true,
        'default_cache_ttl' => 3600, // seconds
        'log_operations' => env('APIFORGE_LOG_VIRTUAL_FIELDS', false),
        'throw_on_failure' => true,
        'validate_return_types' => env('APIFORGE_VF_VALIDATE_RETURN_TYPES', false),
        
        // Cache configuration
        'cache_key_prefix' => 'vf_',
        'cache_store' => null, // Use default cache store
        'cache_tags' => ['virtual_fields'],
        
        // Performance limits
        'memory_limit' => 128, // MB
        'time_limit' => 30, // seconds
        'max_sort_records' => 10000, // Maximum records for virtual field sorting
        'batch_size' => 100, // Batch size for processing large datasets
        
        // Sorting configuration
        'allow_sorting' => true,
        'sort_fallback_enabled' => true, // Fall back to regular sorting if virtual field sorting fails
        'sort_cache_enabled' => true, // Cache sorted results
        'sort_cache_ttl' => 1800, // seconds
        
        // Performance monitoring
        'enable_monitoring' => env('APIFORGE_VF_MONITORING', false),
        'log_performance' => env('APIFORGE_VF_LOG_PERFORMANCE', false),
        'log_slow_computations' => true,
        'slow_computation_threshold' => 1000, // milliseconds
        'track_memory_usage' => true,
        'track_computation_time' => true,
        
        // Lazy loading
        'lazy_loading_enabled' => env('APIFORGE_VF_LAZY_LOADING', true),
        
        // Batch processing optimization
        'continue_on_batch_error' => false,
        'strict_limits' => true,
        'force_gc_frequency' => 10, // Force garbage collection every N batches
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Hooks Settings
    |--------------------------------------------------------------------------
    |
    | Configure model hook behavior and execution settings.
    |
    */
    'model_hooks' => [
        'enabled' => true,
        'log_execution' => env('APIFORGE_LOG_HOOKS', false),
        'throw_on_failure' => true,
        
        // Performance limits
        'memory_limit' => 128, // MB
        'time_limit' => 30, // seconds
        'max_retries' => 3,
        
        // Transaction handling
        'use_transactions' => true,
        'rollback_on_failure' => true,
        
        // Monitoring
        'enable_monitoring' => env('APIFORGE_HOOK_MONITORING', false),
        'log_performance' => env('APIFORGE_HOOK_LOG_PERFORMANCE', false),
        'log_slow_execution' => true,
        'slow_execution_threshold' => 1000, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling Settings
    |--------------------------------------------------------------------------
    |
    | Configure error handling behavior for virtual fields and model hooks.
    |
    */
    'error_handling' => [
        'throw_on_failure' => env('APIFORGE_THROW_ON_FAILURE', true),
        'log_errors' => env('APIFORGE_LOG_ERRORS', true),
        'use_transactions' => env('APIFORGE_USE_TRANSACTIONS', true),
        'max_retries' => env('APIFORGE_MAX_RETRIES', 3),
        
        // Error rate monitoring
        'monitor_error_rate' => env('APIFORGE_MONITOR_ERROR_RATE', true),
        'error_rate_threshold' => env('APIFORGE_ERROR_RATE_THRESHOLD', 10), // errors per minute
        'error_rate_window' => env('APIFORGE_ERROR_RATE_WINDOW', 60), // seconds
        
        // Timeout settings
        'default_timeout' => env('APIFORGE_DEFAULT_TIMEOUT', 30), // seconds
        'virtual_field_timeout' => env('APIFORGE_VF_TIMEOUT', 30), // seconds
        'hook_timeout' => env('APIFORGE_HOOK_TIMEOUT', 30), // seconds
        
        // Memory limits
        'default_memory_limit' => env('APIFORGE_DEFAULT_MEMORY_LIMIT', 128), // MB
        'virtual_field_memory_limit' => env('APIFORGE_VF_MEMORY_LIMIT', 128), // MB
        'hook_memory_limit' => env('APIFORGE_HOOK_MEMORY_LIMIT', 128), // MB
        
        // Retry configuration
        'retry_delay_base' => 100, // milliseconds (exponential backoff base)
        'retry_delay_max' => 5000, // milliseconds (maximum delay)
        'retry_on_timeout' => true,
        'retry_on_memory_limit' => false,
        
        // Logging configuration
        'log_level' => env('APIFORGE_ERROR_LOG_LEVEL', 'error'),
        'include_stack_trace' => env('APIFORGE_INCLUDE_STACK_TRACE', false),
        'include_context' => env('APIFORGE_INCLUDE_ERROR_CONTEXT', true),
        'log_to_separate_channel' => env('APIFORGE_SEPARATE_ERROR_LOG', false),
        'error_log_channel' => env('APIFORGE_ERROR_LOG_CHANNEL', 'apiforge'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Validation Settings
    |--------------------------------------------------------------------------
    |
    | Configure startup validation behavior for virtual fields and hooks.
    |
    */
    'validation' => [
        'enabled' => env('APIFORGE_VALIDATION_ENABLED', true),
        'throw_on_failure' => env('APIFORGE_VALIDATION_THROW_ON_FAILURE', true),
        'log_validation' => env('APIFORGE_LOG_VALIDATION', true),
        
        // Startup validation
        'validate_on_startup' => env('APIFORGE_VALIDATE_ON_STARTUP', true),
        'validate_virtual_fields' => env('APIFORGE_VALIDATE_VIRTUAL_FIELDS', true),
        'validate_model_hooks' => env('APIFORGE_VALIDATE_MODEL_HOOKS', true),
        
        // Runtime validation
        'validate_configurations' => env('APIFORGE_VALIDATE_CONFIGURATIONS', true),
        'validate_dependencies' => env('APIFORGE_VALIDATE_DEPENDENCIES', true),
        'validate_return_types' => env('APIFORGE_VALIDATE_RETURN_TYPES', false),
        
        // Validation reporting
        'generate_suggestions' => env('APIFORGE_GENERATE_SUGGESTIONS', true),
        'include_warnings' => env('APIFORGE_INCLUDE_WARNINGS', true),
        'detailed_error_messages' => env('APIFORGE_DETAILED_ERROR_MESSAGES', true),
    ],
];