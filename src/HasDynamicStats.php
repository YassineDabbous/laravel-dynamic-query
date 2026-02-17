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
     * Main Entry Point
     */
    public function scopeDynamicStats(Builder $q)
    {
        $request = request();

        // Config: Settings
        $enableCache = config('dynamic-query.settings.enable_stats_cache', true);
        $cacheTtl = config('dynamic-query.defaults.cache_ttl', 600);

        // Config: Params
        // We fetch these here to ensure the fingerprint includes all relevant params
        $params = $request->all();

        if ($enableCache && $request->isMethod('get')) {
            // Generate Fingerprint
            ksort($params);
            $hash = 'stats:' . $this->getTable() . ':' . md5(json_encode($params));
            return Cache::remember($hash, $cacheTtl, fn() => $this->runStatsPipeline($q));
        }

        return $this->runStatsPipeline($q);
    }

    /**
     * API Friendly Wrapper
     */
    public function scopeDynamicStatsAPI(Builder $q): array
    {
        $rawData = $this->scopeDynamicStats($q);
        return StatsTransformer::make($rawData);
    }

    protected function runStatsPipeline(Builder $q)
    {
        $request = request();

        // Config: Params
        $pCompare = config('dynamic-query.params.compare', '_compare');
        $pMetric = config('dynamic-query.params.metric', '_metric');
        $pTransform = config('dynamic-query.params.transform', '_transform');

        // 1. Compare Mode (Period-over-Period)
        if ($request->input($pCompare) === 'previous_period') {
            return $this->runComparison($q);
        }

        // 2. Apply Grouping (Delegated to HasDynamicGroup)
        // This handles Smart Joins, Date Macros, and Selects
        $q->dynamicGroupBy();

        // 3. Apply Filters (Delegated to HasDynamicFilter)
        $q->dynamicFilter();

        // 4. Select Metric
        $metricAlias = 'value';
        $this->applyStatsMetric($q, $request->input($pMetric, 'count'), $metricAlias);

        // 5. Execute
        $data = $q->get();

        // 6. Post-Transform
        if ($transform = $request->input($pTransform)) {
            $data = $this->transformStats($data, $transform, $metricAlias);
        }

        return $data;
    }

    protected function applyStatsMetric(Builder $q, $input, $alias)
    {
        [$type, $field] = array_pad(explode(':', $input), 2, null);

        // A. Custom Metrics
        if (isset($this->dynamicMetrics()[$type])) {
            return $q->selectRaw("({$this->dynamicMetrics()[$type]}) as $alias");
        }

        // B. Standard Aggregates
        $allowed = ['count', 'sum', 'avg', 'min', 'max'];
        if (!in_array($type, $allowed)) {
            $type = 'count';
        }

        // Qualify column (e.g. 'total' -> 'orders.total')
        $columnSql = $field ? $this->dynamicQualifyColumn($q, $field) : '*';

        // Casting for clean JSON output
        $cast = in_array($type, ['avg']) ? 'DECIMAL(10,2)' : 'SIGNED';

        if ($type === 'count') {
            $q->selectRaw("COUNT($columnSql) as $alias");
        } else {
            $q->selectRaw("CAST($type($columnSql) AS $cast) as $alias");
        }
    }

    protected function runComparison(Builder $originalQuery)
    {
        $request = request();
        $pCompare = config('dynamic-query.params.compare', '_compare');
        $pCompareOn = config('dynamic-query.params.compare_on', '_compare_on');

        // 1. Determine which Date Column controls the period
        $dateCol = $request->input($pCompareOn, 'created_at');

        // 2. Validate that we actually have a Date Range to shift
        // The comparison logic REQUIRES a range (Array of 2 dates) in the request input.
        $dateRange = $request->input($dateCol);

        if (!is_array($dateRange) || count($dateRange) !== 2) {
            // If no valid range is provided, we cannot calculate the "Previous" period.
            // Return only current data to prevent crashing.
            request()->merge([$pCompare => null]);
            return [
                'current' => $this->runStatsPipeline($originalQuery->clone()),
                'previous' => [],
                'summary' => null,
                'error' => "Comparison requires a date range filter on '$dateCol'."
            ];
        }

        // 3. Run Current Period
        $currentQuery = $originalQuery->clone();
        request()->merge([$pCompare => null]);
        $currentData = $this->runStatsPipeline($currentQuery);

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
            // Temporarily override the request input for the date column
            request()->merge([
                $dateCol => [$prevStart->toDateTimeString(), $prevEnd->toDateTimeString()]
            ]);

            $previousQuery = $originalQuery->clone();

            // Note: In real app, cleaner to pass $request object explicitly to runStatsPipeline
            $previousData = $this->runStatsPipeline($previousQuery);

            // 6. Restore Request State (Cleanup)
            request()->merge([
                $dateCol => $dateRange,
                $pCompare => 'previous_period'
            ]);

            return [
                'current' => $currentData,
                'previous' => $previousData,
                'summary' => $this->calculateSummaryDelta($currentData, $previousData)
            ];

        } catch (\Exception $e) {
            // Restore request if date parsing fails
            request()->merge([$dateCol => $dateRange, $pCompare => 'previous_period']);
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