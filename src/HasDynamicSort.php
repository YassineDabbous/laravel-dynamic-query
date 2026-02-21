<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder;

trait HasDynamicSort {
    use HasDynamicCore;

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
     
    /** Change OrderBy clause. */
    public function scopeDynamicSort(Builder $q, array $allowed = [], array $ignore = [], array $input = []): Builder {
        $input = $this->resolveDynamicInput($input);
        $pSort = config('dynamic-query.params.sort', '_sort');
        $requested = $input[$pSort] ?? [];
        $list = is_array($requested) ? $requested : explode(',', $requested);
        $list = array_filter(array_map('trim', $list));

        if(count($list)){
            $allSorts = count($allowed) ? $allowed : $this->dynamicSorts();
            $allowedSorts = array_filter($allSorts, fn($k) => !in_array($k, $ignore));
            $allowedSorts = $this->normalizeAssociativeArray($allowedSorts);
            $allowedNames = array_keys($allowedSorts);

            $requestedSorts = [];
            foreach ($list as $value) {
                if(str_starts_with($value, '-')){
                    $requestedSorts[str_replace('-', '', $value)] = 'desc';
                } else {
                    $requestedSorts[$value] = 'asc';
                }
            }
    
            $filtered = array_intersect_key($requestedSorts, $allowedSorts);
    
            foreach ($filtered as $column => $direction) {
                $q->orderBy($column, $direction);
            }
        }

        return $q;
    }

    /** @deprecated Use scopeDynamicSort instead. */
    public function scopeDynamicOrderBy(Builder $q, array $allowed = [], array $default = [], array $ignore = [], array $input = []): Builder {
        // If default sorts are provided, apply them first if no sort is requested
        $input = $this->resolveDynamicInput($input);
        $pSort = config('dynamic-query.params.sort', '_sort');
        $requested = $input[$pSort] ?? [];
        $list = is_array($requested) ? $requested : explode(',', $requested);
        $list = array_filter(array_map('trim', $list));

        if (empty($list) && count($default)) {
            $sorts = $this->toAssociative($default);
            foreach ($sorts as $column => $direction) {
                $q->orderBy($column, $direction == 'desc' ? 'desc' : 'asc'); 
            }
        }
        
        return $this->scopeDynamicSort($q, $allowed, $ignore, $input);
    }
}
