<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StatsTransformer
{
    protected $input;
    protected $data;
    
    public function __construct($data, array $input = [])
    {
        $this->input = !empty($input) ? $input : (request() ? request()->all() : []);
        $this->data = $data;
    }

    public static function make($data, array $input = []): array
    {
        return (new static($data, $input))->resolve();
    }

    public function resolve(): array
    {
        // 1. Normalize Data (Handle Compare Mode vs Standard Mode)
        $isComparison = isset($this->data['current']) && isset($this->data['previous']);
        
        $current = $isComparison ? Collection::make($this->data['current']) : Collection::make($this->data);
        $previous = $isComparison ? Collection::make($this->data['previous']) : Collection::make([]);
        
        $summaryOverride = null;
        if ($isComparison && !empty($this->data['summary'])) {
            $summaryOverride = $this->data['summary'];
        }

        // 2. Build Dataset
        $dataset = $this->buildDataset($current, $previous);

        // 3. Calculate Summary (if not provided by delta logic)
        $summary = $summaryOverride ?? $this->calculateSummary($dataset);
        
        // Defensive check: If calculateSummary somehow returns empty, force null
        if (empty($summary)) {
            $summary = null;
        }

        return [
            'meta' => $this->buildMeta(),
            'summary' => $summary,
            'dataset' => $dataset,
        ];
    }

    protected function buildDataset(Collection $current, Collection $previous): array
    {
        // Determine grouping keys to match previous data with current
        // If _group=status,created_at:month, keys are status, created_at_month
        $pGroup = config('dynamic-query.params.group', '_group');
        $groupParams = $this->input[$pGroup] ?? null;
        $groupKeys = [];
        
        if ($groupParams) {
            $rawGroups = is_array($groupParams) ? $groupParams : explode(',', $groupParams);
            foreach ($rawGroups as $g) {
                [$f, $m] = array_pad(explode(':', $g), 2, null);
                // Convert dot notation to underscore for alias matching (user.id -> user_id)
                $alias = str_replace('.', '_', $f) . ($m ? "_$m" : '');
                $groupKeys[] = $alias;
            }
        }

        return $current->map(function ($item) use ($previous, $groupKeys) {
            $item = (object) $item;
            $value = $item->value ?? 0;
            
            // Find Matching Previous Item
            $prevItem = null;
            if ($previous->isNotEmpty() && !empty($groupKeys)) {
                $prevItem = $previous->first(function ($p) use ($item, $groupKeys) {
                    foreach ($groupKeys as $key) {
                        if (($p->$key ?? null) != ($item->$key ?? null)) return false;
                    }
                    return true;
                });
            }

            // Extract Groups
            $groups = [];
            $labelParts = [];
            foreach ($groupKeys as $key) {
                $val = $item->$key ?? null;
                $groups[$key] = $val;
                if ($val) $labelParts[] = $val;
            }

            // Extract Transforms
            $transforms = [];
            if (isset($item->cumulative_value)) $transforms['cumulative'] = $item->cumulative_value;
            if (isset($item->growth_percentage)) $transforms['growth'] = $item->growth_percentage;

            return [
                'label' => empty($labelParts) ? 'Total' : implode(' - ', $labelParts),
                'group' => (object) $groups, // Cast to object for JSON {}
                'value' => (float) $value,
                'previous_value' => $prevItem ? (float) ($prevItem->value ?? 0) : null,
                'transforms' => (object) $transforms,
            ];
        })->values()->toArray();
    }

    protected function calculateSummary(array $dataset): array
    {
        $total = Collection::make($dataset)->sum('value');
        
        // Check if we are doing an Average metric, summing it is wrong.
        // But for generic API, Sum is the safest default summary unless stated otherwise.
        $pMetric = config('dynamic-query.params.metric', '_metric');
        $metric = $this->input[$pMetric] ?? 'count';
        if (Str::startsWith($metric, 'avg')) {
            $total = count($dataset) ? $total / count($dataset) : 0;
        }

        return [
            'value' => $total,
            'formatted' => (string) round($total, 2), // Can add currency formatting logic here later
        ];
    }

    protected function buildMeta(): array
    {
        $pMetric    = config('dynamic-query.params.metric', '_metric');
        $pTimezone  = config('dynamic-query.params.timezone', '_timezone');
        $pGroup     = config('dynamic-query.params.group', '_group');

        return [
            'metric' => $this->input[$pMetric] ?? 'count',
            'currency' => config('app.currency', 'USD'), // Or from request
            'timezone' => $this->input[$pTimezone] ?? 'UTC',
            'granularity' => $this->input[$pGroup] ?? null,
        ];
    }
}