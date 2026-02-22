<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder;

trait HasDynamicQuery{
    
    use HasDynamicFields, HasDynamicFilter, HasDynamicSort, HasDynamicGroup, HasDynamicStats, Helpers\HasDatePresets;

    /** One method for all scopes. */
    /**
     * Apply all dynamic features (Select, Filter, Sort, Group) at once.
     * 
     * @param Builder $q
     * @param array $input Optional input data (defaults to request()->all())
     * @return mixed
     */
    public function scopeDynamicQuery(Builder $q, array $input = []): Builder{
        return $q->dynamicSelect([], [], $input)->dynamicFilter([], [], [], $input)->dynamicSort([], [], $input)->dynamicGroupBy([], [], [], $input);
    }
    
    /**
     * API Friendly Wrapper: applies all dynamic features, paginates, 
     * and appends results in a single call.
     * 
     * @param Builder $q
     * @param array $input Optional input data (defaults to request()->all())
     * @return mixed
     */
    public function scopeDynamicAPI(Builder $q, array $input = []): mixed{
        $result = $q->dynamicSelect([], [], $input)
                    ->dynamicFilter([], [], [], $input)
                    ->dynamicSort([], [], $input)
                    ->dynamicGroupBy([], [], [], $input)
                    ->dynamicPaginate([], $input);
        
        if (method_exists($result, 'dynamicAppend')) {
            $result->dynamicAppend([], [], $input);
        }
        
        return $result;
    }
}
