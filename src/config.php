<?php

return [

    'defaults' => [
        'per_page'      => 15,
        'max_per_page'  => 100,
        'allow_get_all' => false,
        'max_get_all'   => 1000,
        'cache_ttl'     => 600, // 10 minutes for stats
        'timezone'      => 'UTC',
        'currency'      => 'USD',
    ],

    'settings' => [
        'enable_stats_cache' => false,
        'relation_guess'     => true, // Auto-discover relations via Reflection
        'strict_filtering'   => true, // Only allow columns defined in dynamicFilters
        'clean_response'     => true, // set visible attributes from request fields list 
    ],
    
    /** url params names */
    'params' => [
        'model'     => '_model',
        'fields'    => '_fields',
        'logic'     => '_logic',
        'operators' => '_operators',
        'sort'      => '_sort',
        'limit'     => '_limit',
        'per_page'  => 'per_page',
        // NOTE: 'page' param name is controlled by Laravel's paginator, not by this package.
        'get_all'   => '_get_all',
        'clause'    => '_clause',
        'clauses'   => '_clauses',
        
        // Stats & Grouping Params
        'metric'     => '_metric',
        'col'        => '_col',
        'group'      => '_group',
        'period'     => '_period',
        'alias'      => '_alias',
        'transform'  => '_transform', // cumulative, growth
        'compare'    => '_compare',   // previous_period
        'compare_on' => '_compare_on', 
        'timezone'   => '_timezone',  // Asia/Tokyo
        'cache'      => '_cache',
        'simple'     => '_simple',    // true/false for simplePaginate
    ],


];
