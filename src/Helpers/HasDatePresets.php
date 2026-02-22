<?php

namespace YassineDabbous\DynamicQuery\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait HasDatePresets
{
    /**
     * Override to add more date columns that support presets.
     * Each entry creates a scope named scope{StudlyCase}().
     * 
     * @return array
     */
    protected function datePresetColumns(): array
    {
        return ['created_at'];
    }

    /**
     * Intercepts filters on 'created_at' to handle semantic strings.
     * Signature must match HasDynamicFilter calling signature.
     */
    public function scopeCreatedAt(Builder $query, mixed $value, ?string $operator, string $logic, bool $not, string $clause): Builder
    {
        $table = $this->getTable();
        return $this->applyDateScope($query, "$table.created_at", $value, $operator, $logic, $not);
    }

    /**
     * Helper to apply date logic to any column (e.g. delivery_date)
     */
    public function applyDatePreset(Builder $query, string $column, mixed $value, ?string $operator, string $logic, bool $not): Builder
    {
        return $this->applyDateScope($query, $column, $value, $operator, $logic, $not);
    }

    /**
     * Helper to apply date logic to any column (e.g. delivery_date)
     */
    protected function applyDateScope(Builder $query, string $column, mixed $value, ?string $operator, string $logic, bool $not): Builder
    {
        // 1. Handle Semantic Presets (Strings)
        if (is_string($value)) {
            $now = Carbon::now(config('app.timezone', 'UTC'));

            $range = match($value) {
                'today'         => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'yesterday'     => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
                'this_week'     => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                'last_week'     => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
                'this_month'    => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'last_month'    => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
                'this_year'     => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
                'last_year'     => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
                'last_7_days'   => [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()],
                'last_30_days'  => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
                default         => null
            };

            if ($range) {
                return $query->whereBetween($column, $range, $logic, $not);
            }
        }

        // 2. Fallback: Apply Standard Logic
        if (is_array($value) && count($value) === 2) {
             return $query->whereBetween($column, $value, $logic, $not);
        }

        if ($not) {
            return $query->whereNot($column, $operator, $value, $logic);
        }
        
        return $query->where($column, (string) $operator, $value, $logic);
    }
}
