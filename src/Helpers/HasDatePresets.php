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
        // Handle Semantic Presets (Strings)
        if (is_string($value)) {
            $now = Carbon::now(config('app.timezone', 'UTC'));

            $range = match($value) {
                'today'         => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'yesterday'     => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
                'this_week'     => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                'last_week'     => [$now->copy()->subDays(7), $now->copy()],
                'this_month'    => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'last_month'    => [$now->copy()->subDays(31)->startOfDay(), $now->copy()->endOfDay()],
                'this_year'     => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
                'last_year'     => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
                'last_7_days'   => [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()],
                'last_30_days'  => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
                'ytd'           => [$now->copy()->startOfYear(), $now->copy()],
                'qtd'           => [$now->copy()->startOfQuarter(), $now->copy()],
                'mtd'           => [$now->copy()->startOfMonth(), $now->copy()],
                default         => null
            };

            if ($range) {
                if (str_contains($column, '->') && $query->getConnection()->getDriverName() === 'sqlite') {
                    // Strips table qualification if present: posts.meta->key => meta->key
                    $cleanCol = str_contains($column, '.') ? explode('.', $column)[1] : $column;
                    [$col, $path] = explode('->', $cleanCol, 2);
                    $path = '$' . (str_starts_with($path, '$') ? '' : '.') . str_replace('->', '.', $path);
                    $column = \Illuminate\Support\Facades\DB::raw("json_extract($col, '$path')");
                }
                $range = array_map(fn($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d H:i:s') : $d, $range);
                return $query->whereBetween($column, $range, $logic, $not);
            }
            
            // If it was a string but not a preset, we check if it's a date or just skip if we want strict presets
            // Tests expect that invalid presets are ignored (returning all records)
            return $query;
        }

        // Handle Array Ranges (Length 2)
        if (is_array($value) && count($value) === 2) {
             $start = $value[0];
             $end = $value[1];
             
             // If end date has no time, make it end of day
             if (is_string($end) && strlen($end) <= 10) {
                 $end .= ' 23:59:59';
             }
             
             return $query->whereBetween($column, [$start, $end], $logic, $not);
        }
        
        // Single value array (e.g. ['2024-01-15']) -> treat as full day
        if (is_array($value) && count($value) === 1) {
             $date = reset($value);
             if (is_string($date) && strlen($date) <= 10) {
                 return $query->whereBetween($column, [$date . ' 00:00:00', $date . ' 23:59:59'], $logic, $not);
             }
             $value = $date;
        }

        if ($not) {
            if (str_contains($column, '->') && $query->getConnection()->getDriverName() === 'sqlite') {
                $cleanCol = str_contains($column, '.') ? explode('.', $column)[1] : $column;
                [$col, $path] = explode('->', $cleanCol, 2);
                $path = '$' . (str_starts_with($path, '$') ? '' : '.') . str_replace('->', '.', $path);
                $column = \Illuminate\Support\Facades\DB::raw("json_extract($col, '$path')");
            }
            return $query->whereNot($column, $operator, $value, $logic);
        }
        
        if (str_contains($column, '->') && $query->getConnection()->getDriverName() === 'sqlite') {
            $cleanCol = str_contains($column, '.') ? explode('.', $column)[1] : $column;
            [$col, $path] = explode('->', $cleanCol, 2);
            $path = '$' . (str_starts_with($path, '$') ? '' : '.') . str_replace('->', '.', $path);
            $column = \Illuminate\Support\Facades\DB::raw("json_extract($col, '$path')");
        }
        return $query->where($column, (string) $operator, $value, $logic);
    }
}
