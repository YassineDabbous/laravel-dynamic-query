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
    public function scopeDynamicSort(Builder $q, ?array $input = [], ?array $allowed = null, ?array $default = null, ?array $ignore = null): Builder {
        $input = $this->resolveDynamicInput($input ?? []);
        $allowed ??= [];
        $default ??= [];
        $ignore ??= [];
        $pSort = config('dynamic-query.params.sort', '_sort');
        $requested = $input[$pSort] ?? [];
        $list = is_array($requested) ? $requested : explode(',', $requested);
        $list = array_filter(array_map('trim', $list));

        if (empty($list) && !empty($default)) {
            $list = [];
            foreach ($default as $key => $value) {
                if (is_numeric($key)) {
                    $list[] = $value;
                } else {
                    $list[] = ($value === 'desc' ? '-' : '') . $key;
                }
            }
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
        ?array $input = [],
        ?array $allowed = [],
        ?array $default = [],
        ?array $ignore = []
    ): Builder {
        return $this->scopeDynamicSort($q, $input, $allowed, $default, $ignore);
    }
}
