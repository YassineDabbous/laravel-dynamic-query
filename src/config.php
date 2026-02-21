<?php

return [

    'defaults' => [
        'per_page'      => 15,
        'max_per_page'  => 100,
        'allow_get_all' => false,
        'max_get_all'   => 1000,
        'cache_ttl'     => 600, // 10 minutes for stats
        'timezone'      => 'UTC',
    ],

    'settings' => [
        'enable_stats_cache' => false,
        'relation_guess'     => true, // Auto-discover relations via Reflection
        'strict_filtering'   => true, // Only allow columns defined in dynamicFilters
        'clean_response' => true,     // set visible attributes from request fields list 
    ],
    
    /** url params names */
    'params' => [
        'model'     => '_model',
        'fields'    => '_fields',
        'logic'     => '_logic',
        'operators' => '_operators',
        'sort'      => '_sort',
        'limit'     => '_limit',
        'page'      => 'page',
        'per_page'  => 'per_page',
        'get_all'   => '_get_all',
        'clause'    => '_clause',
        'clauses'   => '_clauses',
        
        // Stats Params
        'metric' => '_metric',
        'group' => '_group',
        'transform' => '_transform', // cumulative, growth
        'compare' => '_compare',     // previous_period
        'compare_on' => '_compare_on', 
        'timezone' => '_timezone',   // Asia/Tokyo
        'simple' => '_simple',       // true/false for simplePaginate
    ],

    // Reserved for future use:
    'filter' => [],

    'fields' => [
        /** Fields delimiter for default format */
        'delimiter' => ',',

        /*
        |--------------------------------------------------------------------------
        | Fields parsing format.
        |--------------------------------------------------------------------------
        |
        | Available Formats (Planned for future):
        |      - null      => Default format    (eg: "id,name,posts:id|title,created_at")
        |      - yaml      => Inline yaml       (eg: "- id\n- name\n- posts:\n    - id\n    - title")
        |      - json      => inline json       (eg: ["id", "name", { "posts": ["id", "title"] }])
        |
        */
        'format' => null,
    ],

];
