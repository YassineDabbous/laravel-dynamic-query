<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

trait HasDynamicStats
{

    /**
     * Define custom SQL metrics (Category B - Ratios/SQL).
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
     */
    public function scopeDynamicStats(Builder $q, array $input = [])
    {
        $input = $this->resolveDynamicInput($input);

        // Config: Settings
        $enableCache = config('dynamic-query.settings.enable_stats_cache', true);
        $cacheTtl = config('dynamic-query.defaults.cache_ttl', 600);

        // Config: Params
        // We fetch these here to ensure the fingerprint includes all relevant params
        $hashParams = $input;

        if ($enableCache && request() && request()->isMethod('get')) {
            // Generate Fingerprint
            $hashParams = $input;
            ksort($hashParams);
            $hash = 'stats:' . $this->getTable() . ':' . md5(json_encode($hashParams));
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
    protected function runStatsPipeline(Builder $q, array $input = [])
    {

        // Config: Params
        $pCompare = config('dynamic-query.params.compare', '_compare');
        $pMetric = config('dynamic-query.params.metric', '_metric');
        $pTransform = config('dynamic-query.params.transform', '_transform');

        // 1. Compare Mode (Period-over-Period)
        if (($input[$pCompare] ?? null) === 'previous_period') {
            return $this->runComparison($q, $input);
        }

        // 2. Apply Grouping (Delegated to HasDynamicGroup)
        // This handles Smart Joins, Date Macros, and Selects
        $q->dynamicGroupBy([], [], [], $input);

        // 3. Apply Filters (Delegated to HasDynamicFilter)
        $q->dynamicFilter([], [], [], $input);

        // 4. Select Metric
        $metricAlias = 'value';
        $this->applyStatsMetric($q, $input[$pMetric] ?? 'count', $metricAlias);

        // 5. Execute
        $data = $q->get();

        // 6. Post-Transform
        if ($transform = ($input[$pTransform] ?? null)) {
            $data = $this->transformStats($data, $transform, $metricAlias);
        }

        return $data;
    }

    protected function applyStatsMetric(Builder $q, $input, $alias)
    {
        [$type, $field] = array_pad(explode(':', $input), 2, null);

        // Sanitize alias to prevent SQL injection
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '_', $alias);

        // A. Custom Metrics
        $metrics = $this->dynamicMetrics();
        if (isset($metrics[$type])) {
            return $q->selectRaw("({$metrics[$type]}) as $alias");
        }

        // B. Standard Aggregates
        $allowed = ['count', 'sum', 'avg', 'min', 'max'];
        if (!in_array($type, $allowed)) {
            $type = 'count';
        }

        // Validate that $field is a known column
        $columnSql = '*';
        if ($field) {
            $allowedColumns = method_exists($this, 'dynamicColumns') ? $this->dynamicColumns() : [];
            if (!empty($allowedColumns) && !in_array($field, $allowedColumns)) {
                $field = null; // Fall back to COUNT(*)
            }
            $columnSql = $field ? $this->dynamicQualifyColumn($q, $field) : '*';
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

    /**
     * Run comparative statistics (Period-over-Period).
     * Shifts the date window backwards by the same duration as the current range.
     * 
     * @param Builder $originalQuery
     * @param array $input
     */
    protected function runComparison(Builder $originalQuery, array $input = [])
    {
        $pCompare = config('dynamic-query.params.compare', '_compare');
        $pCompareOn = config('dynamic-query.params.compare_on', '_compare_on');

        // 1. Determine which Date Column controls the period
        $dateCol = $input[$pCompareOn] ?? 'created_at';

        // 2. Validate that we actually have a Date Range to shift
        // The comparison logic REQUIRES a range (Array of 2 dates) in the request input.
        $dateRange = $input[$dateCol] ?? null;

        if (!is_array($dateRange) || count($dateRange) !== 2) {
            // If no valid range is provided, we cannot calculate the "Previous" period.
            // Return only current data to prevent crashing.
            $errorInput = $input;
            $errorInput[$pCompare] = null;
            return [
                'current' => $this->runStatsPipeline($originalQuery->clone(), $errorInput),
                'previous' => [],
                'summary' => null,
                'error' => "Comparison requires a date range filter on '$dateCol'."
            ];
        }

        // 3. Run Current Period
        $currentInput = $input;
        $currentInput[$pCompare] = null;
        $currentData = $this->runStatsPipeline($originalQuery->clone(), $currentInput);

        // 4. Calculate Previous Dates
        try {
            $start = Carbon::parse($dateRange[0]);
            $end = Carbon::parse($dateRange[1]);

            // Calculate duration in days (inclusive)
            $days = $start->diffInDays($end) + 1;

            // Shift window backwards
            $prevStart = $start->copy()->subDays($days);
            $prevEnd = $end->copy()->subDays($days);

            // 5. Run Previous Period Query
            $prevInput = $input;
            $prevInput[$dateCol] = [$prevStart->toDateTimeString(), $prevEnd->toDateTimeString()];
            $prevInput[$pCompare] = null;

            $previousData = $this->runStatsPipeline($originalQuery->clone(), $prevInput);

            return [
                'current' => $currentData,
                'previous' => $previousData,
                'summary' => $this->calculateSummaryDelta($currentData, $previousData)
            ];

        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function transformStats($collection, $transform, $valueKey)
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
            $prev = 0;
            return $collection->map(function ($item) use (&$prev, $valueKey) {
                $curr = $item->$valueKey;
                $diff = ($prev == 0) ? 0 : (($curr - $prev) / abs($prev)) * 100;
                $item->growth_percentage = round($diff, 2);
                $prev = $curr;
                return $item;
            });
        }

        return $collection;
    }

    protected function calculateSummaryDelta($current, $previous)
    {
        // Simple scalar delta logic
        // If the result is a Collection (grouped), this is harder to generalize,
        // so we return null or generic structure.
        if (count($current) == 1 && isset($current[0]->value) && count($previous) == 1) {
            $currVal = $current[0]->value;
            $prevVal = $previous[0]->value ?? 0;

            return [
                'absolute' => $currVal - $prevVal,
                'percent' => $prevVal > 0 ? round((($currVal - $prevVal) / $prevVal) * 100, 2) : 0
            ];
        }
        return null;
    }
}