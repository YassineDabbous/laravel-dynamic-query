<?php

namespace YassineDabbous\DynamicQuery\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait HasDatePresets
{
    /**
     * Intercepts filters on 'created_at' to handle semantic strings.
     * Signature must match HasDynamicFilter calling signature.
     */
    public function scopeCreatedAt(Builder $query, $value, $operator, $logic, $not, $clause)
    {
        $table = $this->getTable();
        return $this->applyDateScope($query, "$table.created_at", $value, $operator, $logic, $not);
    }

    /**
     * Helper to apply date logic to any column (e.g. delivery_date)
     */
    protected function applyDateScope(Builder $query, $column, $value, $operator, $logic, $not)
    {
        // 1. Handle Semantic Presets (Strings)
        if (is_string($value)) {
            $range = match($value) {
                'today'         => [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()],
                'yesterday'     => [Carbon::now()->subDay()->startOfDay(), Carbon::now()->subDay()->endOfDay()],
                'this_week'     => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
                'last_week'     => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()],
                'this_month'    => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
                'last_month'    => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
                'this_year'     => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
                'last_year'     => [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear()],
                'last_7_days'   => [Carbon::now()->subDays(7)->startOfDay(), Carbon::now()->endOfDay()],
                'last_30_days'  => [Carbon::now()->subDays(30)->startOfDay(), Carbon::now()->endOfDay()],
                default         => null
            };

            if ($range) {
                return $query->whereBetween($column, $range, $logic, $not);
            }
        }

        // 2. Fallback: Apply Standard Logic
        // Since we defined this scope, the library won't run its default logic for this column.
        // We must manually implement the standard behavior for Arrays (Ranges) or simple Dates.
        
        if (is_array($value) && count($value) === 2) {
             // It's a range array ['2023-01-01', '2023-02-01']
             return $query->whereBetween($column, $value, $logic, $not);
        }

        // It's a single date string or operator based comparison
        if ($not) {
            return $query->whereNot($column, $operator, $value, $logic);
        }
        
        return $query->where($column, $operator, $value, $logic);
    }
}