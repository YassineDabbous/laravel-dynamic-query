<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder;

trait HasDynamicGroup {
    use HasDynamicCore;
    use InteractsWithSmartJoins;

    /**
     * Allowed columns for GroupBy clause.
     * Supports Dot Notation for relations (e.g., 'user.country') and Date Macros automatically.
     *
     * Examples:
     *      /endpoint?_group=name,price
     *      /endpoint?_group[]=name&_group[]=price
     *      /endpoint?_group=created_at:month
     * 
     */
    public function dynamicGroups(): array {
        return [];
    }

    public function scopeDynamicGroupBy(Builder $q, array $allowed = [], array $default = [], array $ignore = []): Builder {
        
        /** @var \Illuminate\Http\Request $request */
        $request = request();
        $pGroup = config('dynamic-query.params.group', '_group');

        // Parse Input
        $input = $request->input($pGroup, []);
        if(is_string($input)){
            $input = explode(',', $input);
        }

        // Determine Whitelist
        $whitelist = count($allowed) ? $allowed : $this->dynamicGroups();
        
        // 1. Handle Explicit Groups
        if(count($input)){
            foreach ($input as $rawGroup) {
                // Parse "field:macro" (e.g., created_at:month)
                [$field, $macro] = array_pad(explode(':', $rawGroup), 2, null);

                // Security Check (Check field name without macro)
                if (!in_array($field, $whitelist) || in_array($field, $ignore)) {
                    continue;
                }

                // Smart Join & Qualify
                $qualified = $this->dynamicQualifyColumn($q, $field);

                if ($macro) {
                    // Date Grouping (SQL Generation)
                    $this->applyDateGrouping($q, $qualified, $macro);
                } else {
                    // Standard Grouping
                    $q->groupBy($qualified);
                    // We select the group so it appears in the result set
                    if(!$q->getQuery()->columns || !in_array($qualified, $q->getQuery()->columns)) {
                        $q->addSelect($qualified);
                    }
                }
            }
        } 
        // 2. Handle Defaults
        else if(count($default)){
            foreach ($default as $group) {
                $qualified = $this->dynamicQualifyColumn($q, $group);
                $q->groupBy($qualified);
            }
        }

        return $q;
    }

    /**
     * Generates DB-Specific SQL for Date grouping
     */
    protected function applyDateGrouping(Builder $q, $column, $macro)
    {
        $pTimezone = config('dynamic-query.params.timezone', '_timezone');
        $defaultTz = config('dynamic-query.defaults.timezone', 'UTC');
        
        $tz = request()->input($pTimezone, $defaultTz);
        $driver = $q->getConnection()->getDriverName();
        
        // Alias: created_at_month
        $alias = str_replace('.', '_', $column) . '_' . $macro;

        $sql = match($driver) {
            'pgsql'  => $this->pgDateSql($column, $macro, $tz),
            'sqlite' => $this->sqliteDateSql($column, $macro),
            default  => $this->mysqlDateSql($column, $macro, $tz),
        };

        $q->selectRaw("$sql as $alias")
          ->groupBy($alias)
          ->orderBy($alias);
    }

    // --- SQL Helpers ---

    protected function mysqlDateSql($col, $period, $tz)
    {
        $colSql = ($tz && $tz !== 'UTC') ? "CONVERT_TZ($col, '+00:00', '$tz')" : $col;
        $format = match ($period) {
            'year'  => '%Y',
            'month' => '%Y-%m',
            'day'   => '%Y-%m-%d',
            'hour'  => '%Y-%m-%d %H:00',
            default => '%Y-%m-%d'
        };
        return "DATE_FORMAT($colSql, '$format')";
    }

    protected function pgDateSql($col, $period, $tz)
    {
        $colSql = ($tz && $tz !== 'UTC') ? "($col at time zone 'UTC' at time zone '$tz')" : $col;
        $format = match ($period) {
            'year'  => 'YYYY',
            'month' => 'YYYY-MM',
            'day'   => 'YYYY-MM-DD',
            'hour'  => 'YYYY-MM-DD HH24:00',
            default => 'YYYY-MM-DD'
        };
        return "TO_CHAR($colSql, '$format')";
    }

    protected function sqliteDateSql($col, $period)
    {
        $format = match ($period) {
            'year'  => '%Y',
            'month' => '%Y-%m',
            'day'   => '%Y-%m-%d',
            'hour'  => '%Y-%m-%d %H:00',
            default => '%Y-%m-%d'
        };
        return "strftime('$format', $col)";
    }
}