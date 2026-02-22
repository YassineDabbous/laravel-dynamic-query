<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder;

trait HasDynamicSort {
    use HasDynamicCore;
    use InteractsWithSmartJoins;

    /**
     * Allowed columns for OrderBy clause.
     *
     * Examples:
     *      /endpoint?_sort=id,-price
     *      /endpoint?_sort[]=id&_sort[]=-price
     * 
    */
    public function dynamicSorts(): array {
        return [];
    }
     
    /** 
     * Apply dynamic sorting based on requested fields.
     * Supports dot-notation for related columns (Smart Joins).
     * 
     * @param Builder $q
     * @param array $allowed Override allowed sortable columns
     * @param array $ignore Columns to ignore
     * @param array $input Optional input data (defaults to request()->all())
     * @return Builder
     */
    public function scopeDynamicSort(
        Builder $q,
        array $allowed = [],
        array $default = [],
        array $ignore = [],
        array $input = []
    ): Builder {
        $input = $this->resolveDynamicInput($input);
        $pSort = config('dynamic-query.params.sort', '_sort');
        $requested = $input[$pSort] ?? [];
        $list = is_array($requested) ? $requested : explode(',', $requested);
        $list = array_filter(array_map('trim', $list));

        if (empty($list) && !empty($default)) {
            $list = is_array($default) ? $default : [$default];
        }

        if(count($list)){
            $allSorts = count($allowed) ? $allowed : $this->dynamicSorts();
            $allowedSorts = array_filter($allSorts, fn($k) => !in_array($k, $ignore));
            $allowedSorts = $this->normalizeAssociativeArray($allowedSorts);
            $allowedNames = array_keys($allowedSorts);

            $requestedSorts = [];
            foreach ($list as $value) {
                if(str_starts_with($value, '-')){
                    $requestedSorts[ltrim($value, '-')] = 'desc';
                } else {
                    $requestedSorts[$value] = 'asc';
                }
            }
    
            $filtered = array_intersect_key($requestedSorts, $allowedSorts);
    
            foreach ($filtered as $column => $direction) {
                $qualifiedColumn = $this->dynamicQualifyColumn($q, $column);
                $q->orderBy($qualifiedColumn, $direction);
            }
        }

        return $q;
    }

    /**
     * @deprecated Use scopeDynamicSort() instead.
     */
    public function scopeDynamicOrderBy(
        Builder $q,
        array $allowed = [],
        array $default = [],
        array $ignore = [],
        array $input = []
    ): Builder {
        return $this->scopeDynamicSort($q, $allowed, $default, $ignore, $input);
    }
}
