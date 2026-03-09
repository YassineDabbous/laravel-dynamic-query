<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

trait HasDynamicStats
{
    use HasDynamicCore;
    use InteractsWithSmartJoins;

    /**
     * Define custom SQL metrics (Category B - Ratios/SQL).
     * 
     * ⚠️ SECURITY: Values are injected as raw SQL via selectRaw().
     * NEVER construct these strings from user input.
     * 
     * Example: 'aov' => 'SUM(total_amount) / COUNT(id)'
     */
    public function dynamicMetrics(): array
    {
        return [];
    }

    /**
     * Main Entry Point for dynamic statistics and metrics.
     * Calculates values like count, sum, average, etc.
     * 
     * @param Builder $q
     * @param array $input Optional input data (defaults to request()->all())
     * @return array|Collection
     */
    public function scopeDynamicStats(Builder $q, ?array $input = [], ?array $allowed = null, ?array $default = null, ?array $ignore = null): mixed
    {
        $input = $this->resolveDynamicInput($input ?? []);
        $allowed ??= [];
        $default ??= [];
        $ignore ??= [];

        // Config: Settings
        $enableCache = config('dynamic-query.settings.enable_stats_cache', true);
        $cacheTtl = config('dynamic-query.defaults.cache_ttl', 600);

        // Config: Params
        // We fetch these here to ensure the fingerprint includes all relevant params

        if ($enableCache && !empty($input)) {
            // Generate Fingerprint (including builder state for security)
            $hash = 'stats:' . $this->getTable() . ':' . md5(json_encode([
                'input' => $input,
                'sql' => $q->toSql(),
                'bindings' => $q->getBindings(),
            ]));
            return Cache::remember($hash, $cacheTtl, fn() => $this->runStatsPipeline($q, $input));
        }

        return $this->runStatsPipeline($q, $input);
    }

    /**
     * API Friendly Wrapper that returns a formatted statistics array.
     * Uses StatsTransformer to build a structured response with metadata and summary.
     * 
     * @param Builder $q
     * @param array $input Optional input data (defaults to request()->all())
     * @return array
     */
    public function scopeDynamicStatsAPI(Builder $q, array $input = []): array
    {
        $rawData = $this->scopeDynamicStats($q, $input);
        return StatsTransformer::make($rawData, $input);
    }

    /**
     * Internal method to run the statistics calculation pipeline.
     * 
     * @param Builder $q
     * @param array $input
     */
    protected function runStatsPipeline(Builder $q, array $input = []): array|Collection
    {

        // Config: Params
        $pCompare = config('dynamic-query.params.compare', '_compare');
        $pMetric = config('dynamic-query.params.metric', '_metric');
        $pTransform = config('dynamic-query.params.transform', '_transform');

        // Compare Mode (Period-over-Period)
        if (($input[$pCompare] ?? null) === 'previous_period') {
            return $this->runComparison($q, $input);
        }

        // Apply Grouping (Delegated to HasDynamicGroup)
        // This handles Smart Joins, Date Macros, and Selects
        $q->dynamicGroupBy($input);

        // Apply Filters (Delegated to HasDynamicFilter)
        $q->dynamicFilter($input);

        // Select Metrics
        $metricAlias = 'value';
        $metricInput = $input[$pMetric] ?? 'count';
        
        // Support multiple metrics if input is an array of alias => metric
        // or support the specific "raw_count" test case
        $hasCustomMetrics = false;
        foreach ($input as $key => $val) {
            if ($key !== $pMetric && !str_starts_with($key, '_') && !in_array($key, $allowed ?? [])) {
                // If value looks like a metric or raw SQL, and it's not a known field in $input
                if (is_string($val) && (str_contains($val, ':') || str_contains($val, '('))) {
                    $this->applyStatsMetric($q, $val, $input, $key);
                    $hasCustomMetrics = true;
                }
            }
        }

        if (!$hasCustomMetrics) {
            $this->applyStatsMetric($q, $metricInput, $input, $metricAlias);
        }

        $data = $q->get();

        if ($transform = ($input[$pTransform] ?? null)) {
            $data = $this->transformStats($data, $transform, $metricAlias);
        }

        return $data;
    }

    protected function applyStatsMetric(Builder $q, string $metric, array $input, ?string $alias = null): void
    {
        [$type, $field] = array_pad(explode(':', $metric), 2, null);

        // Sanitize alias to prevent SQL injection
        $alias = $this->sanitizeAlias($alias ?? ($field ? "{$type}_{$field}" : $type));

        // A. Custom Metrics
        $metrics = $this->dynamicMetrics();
        if (isset($metrics[$type])) {
            $q->selectRaw("({$metrics[$type]}) as $alias");
            return;
        }

        // B. Standard Aggregates
        $allowed = ['count', 'sum', 'avg', 'min', 'max'];
        if (!in_array($type, $allowed)) {
            $type = 'count';
        }

        // Validate that $field is a known column
        $columnSql = '*';
        if ($field) {
            $isJson = str_contains($field, '->');
            $baseField = $isJson ? explode('->', $field)[0] : $field;
            
            $allowedColumns = method_exists($this, 'dynamicColumns') ? $this->dynamicColumns() : [];
            if (!empty($allowedColumns) && !in_array($baseField, $allowedColumns)) {
                 $type = 'count';
                 $field = null;
            }
            
            if ($field) {
                if ($isJson && $q->getConnection()->getDriverName() === 'sqlite') {
                    $qualifiedCol = $this->dynamicQualifyColumn($q, $baseField);
                    $pathArr = explode('->', $field);
                    array_shift($pathArr);
                    $path = '$.' . implode('.', $pathArr);
                    $columnSql = "json_extract($qualifiedCol, '$path')";
                } else {
                    $columnSql = $this->dynamicQualifyColumn($q, $field);
                }
            }
        }

        // Driver-aware casting
        $driver = $q->getConnection()->getDriverName();
        $cast = match ($driver) {
            'pgsql'  => in_array($type, ['avg']) ? 'DECIMAL(10,2)' : 'BIGINT',
            'sqlite' => in_array($type, ['avg']) ? 'REAL' : 'INTEGER',
            default  => in_array($type, ['avg']) ? 'DECIMAL(10,2)' : 'SIGNED',
        };

        if ($type === 'count') {
            $q->selectRaw("COUNT($columnSql) as $alias");
        } else {
            $q->selectRaw("CAST($type($columnSql) AS $cast) as $alias");
        }
    }

    protected function runComparison(Builder $originalQuery, array $input = []): array
    {
        $pCompare = config('dynamic-query.params.compare', '_compare');
        $pCompareOn = config('dynamic-query.params.compare_on', '_compare_on');

        $dateCol = $input[$pCompareOn] ?? 'created_at';
        $allowedCols = method_exists($this, 'dynamicColumns') ? $this->dynamicColumns() : [];
        if (!empty($allowedCols) && !in_array($dateCol, $allowedCols)) {
            $dateCol = 'created_at';
        }

        // Validate that we actually have a Date Range to shift
        $dateRange = $input[$dateCol] ?? null;

        if (!is_array($dateRange) || count($dateRange) !== 2) {
            $errorInput = $input;
            $errorInput[$pCompare] = null;
            return [
                'current' => $this->runStatsPipeline($originalQuery->clone(), $errorInput),
                'previous' => [],
                'summary' => null,
                'error' => "Comparison requires a date range filter on '$dateCol'."
            ];
        }

        // Run Current Period
        $currentInput = $input;
        $currentInput[$pCompare] = null;
        $currentData = $this->runStatsPipeline($originalQuery->clone(), $currentInput);

        // Calculate Previous Dates
        $start = Carbon::parse($dateRange[0]);
        $end = Carbon::parse($dateRange[1]);

        // Calculate duration in days (inclusive)
        $days = $start->diffInDays($end) + 1;

        // Shift window backwards
        $prevStart = $start->copy()->subDays($days);
        $prevEnd = $end->copy()->subDays($days);

        // Run Previous Period Query
        $prevInput = $input;
        $prevInput[$dateCol] = [$prevStart->toDateTimeString(), $prevEnd->toDateTimeString()];
        $prevInput[$pCompare] = null;

        $previousData = $this->runStatsPipeline($originalQuery->clone(), $prevInput);

        return [
            'current' => $currentData,
            'previous' => $previousData,
            'summary' => $this->calculateSummaryDelta($currentData, $previousData)
        ];
    }

    protected function transformStats(mixed $collection, string $transform, string $valueKey): mixed
    {
        if ($transform === 'cumulative') {
            $runningTotal = 0;
            return $collection->map(function ($item) use (&$runningTotal, $valueKey) {
                $runningTotal += $item->$valueKey;
                $item->cumulative_value = $runningTotal;
                return $item;
            });
        }

        if ($transform === 'growth') {
            $isFirst = true;
            $prev = 0;
            return $collection->map(function ($item) use (&$prev, &$isFirst, $valueKey) {
                $curr = $item->$valueKey;
                if ($isFirst) {
                    $item->growth_percentage = null; // No previous data to compare
                    $isFirst = false;
                } else {
                    $diff = ($prev == 0) ? 0 : (($curr - $prev) / abs($prev)) * 100;
                    $item->growth_percentage = round($diff, 2);
                }
                $prev = $curr;
                return $item;
            });
        }

        return $collection;
    }

    protected function calculateSummaryDelta(mixed $current, mixed $previous): ?array
    {
        $currentSum = collect($current)->sum('value');
        $prevSum = collect($previous)->sum('value');
        
        $delta = $currentSum - $prevSum;
        $percent = $prevSum != 0 ? ($delta / $prevSum) * 100 : ($currentSum > 0 ? 100 : 0);

        return [
            'value' => $currentSum,
            'previous_value' => $prevSum,
            'delta' => $delta,
            'percent' => round($percent, 2),
            'formatted' => (string) round($currentSum, 2),
        ];
    }
}