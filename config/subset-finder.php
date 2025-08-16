<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Configuration
    |--------------------------------------------------------------------------
    |
    | This is the default configuration for the SubsetFinder package.
    | You can override these values in your application.
    |
    */

    'defaults' => [
        'id_field' => 'id',
        'quantity_field' => 'quantity',
        'sort_field' => 'id',
        'sort_descending' => false,
        'max_memory_usage' => env('SUBSET_FINDER_MAX_MEMORY', 128 * 1024 * 1024), // 128MB
        'enable_lazy_evaluation' => env('SUBSET_FINDER_LAZY_EVALUATION', true),
        'enable_logging' => env('SUBSET_FINDER_LOGGING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Profiles
    |--------------------------------------------------------------------------
    |
    | Pre-configured profiles for different use cases.
    |
    */

    'profiles' => [
        'large_datasets' => [
            'max_memory_usage' => 512 * 1024 * 1024, // 512MB
            'enable_lazy_evaluation' => true,
            'enable_logging' => true,
        ],
        'performance' => [
            'max_memory_usage' => 64 * 1024 * 1024, // 64MB
            'enable_lazy_evaluation' => false,
            'enable_logging' => false,
        ],
        'balanced' => [
            'max_memory_usage' => 256 * 1024 * 1024, // 256MB
            'enable_lazy_evaluation' => true,
            'enable_logging' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for the SubsetFinder package.
    |
    */

    'logging' => [
        'channel' => env('SUBSET_FINDER_LOG_CHANNEL', 'default'),
        'level' => env('SUBSET_FINDER_LOG_LEVEL', 'info'),
        'include_performance_metrics' => env('SUBSET_FINDER_LOG_PERFORMANCE', true),
    ],
];
