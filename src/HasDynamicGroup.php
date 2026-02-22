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

    /**
     * Apply dynamic grouping and date-based group macros.
     * Supports dot-notation for related columns and macro syntax (e.g., field:macro).
     * 
     * @param Builder $q
     * @param array $allowed Whitelist of columns (defaults to dynamicGroups())
     * @param array $default Default grouping if none requested
     * @param array $ignore  Columns to exclude
     * @param array $input   Optional input data (defaults to request()->all())
     * @return Builder
     */
    public function scopeDynamicGroupBy(Builder $q, array $allowed = [], array $default = [], array $ignore = [], array $input = []): Builder {
        $input = $this->resolveDynamicInput($input);
        $pGroup = config('dynamic-query.params.group', '_group');

        // Parse Input
        $requested = $input[$pGroup] ?? [];
        if(is_string($requested)){
            $requested = explode(',', $requested);
        }

        // Determine Whitelist
        $whitelist = count($allowed) ? $allowed : $this->dynamicGroups();
        
        // Handle Explicit Groups
        if(count($requested)){
            foreach ($requested as $rawGroup) {
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
                    $this->applyDateGrouping($q, $qualified, $macro, $input);
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
        // Handle Defaults
        else if(count($default)){
            foreach ($default as $group) {
                // Validate defaults against whitelist if whitelist is defined
                if (!empty($whitelist) && !in_array($group, $whitelist)) {
                    continue;
                }
                $qualified = $this->dynamicQualifyColumn($q, $group);
                $q->groupBy($qualified);
            }
        }

        return $q;
    }

    /**
     * Generates DB-Specific SQL for Date grouping (year, month, day, hour).
     * Handles timezone conversion and automatic alias generation.
     * 
     * @param Builder $q
     * @param string $column Qualified column name
     * @param string $macro  Macro name (year|month|day|hour)
     * @param array  $input  Input data for timezone resolution
     */
    protected function applyDateGrouping(Builder $q, string $column, string $macro, array $input = []): void
    {
        $pTimezone = config('dynamic-query.params.timezone', '_timezone');
        $defaultTz = config('dynamic-query.defaults.timezone', 'UTC');

        $tz = $this->validateTimezone($input[$pTimezone] ?? $defaultTz);
        $macro = $this->validateMacro($macro);
        if (!$macro) {
            return;
        }
        $driver = $q->getConnection()->getDriverName();

        // Alias: created_at_month
        $alias = $this->sanitizeAlias($column . '_' . $macro);

        $sqlData = match ($driver) {
            'mysql'  => $this->mysqlDateSql($column, $macro, $tz),
            'pgsql'  => $this->pgDateSql($column, $macro, $tz),
            'sqlite' => $this->sqliteDateSql($column, $macro),
            default  => null,
        };

        if ($sqlData) {
            [$sql, $bindings] = is_array($sqlData) ? $sqlData : [$sqlData, []];
            
            // NOTE: $bindings is intentionally passed to BOTH calls.
            // Each raw expression has its own '?' placeholder that needs the same $tz value.
            // If you add more '?' to the SQL template, duplicate bindings accordingly.
            $q->groupByRaw($sql, $bindings);
            $q->selectRaw("$sql as $alias", $bindings);
        }
    }

    protected function validateTimezone(string $tz): string
    {
        if (in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            return $tz;
        }

        if (preg_match('/^[+-]\d{2}:\d{2}$/', $tz)) {
            return $tz;
        }

        return 'UTC';
    }

    protected function validateMacro(?string $macro): ?string
    {
        $allowed = ['year', 'month', 'day', 'hour'];
        return in_array($macro, $allowed, true) ? $macro : null;
    }


    // --- SQL Helpers ---

    protected function mysqlDateSql(string $col, string $period, string $tz): array
    {
        $bindings = [];
        if ($tz && $tz !== 'UTC') {
            $colSql = "CONVERT_TZ($col, '+00:00', ?)";
            $bindings[] = $tz;
        } else {
            $colSql = $col;
        }

        $format = '%Y-%m-%d';
        switch ($period) {
            case 'year':  $format = '%Y'; break;
            case 'month': $format = '%Y-%m'; break;
            case 'day':   $format = '%Y-%m-%d'; break;
            case 'hour':  $format = '%Y-%m-%d %H:00'; break;
        }
        
        return ["DATE_FORMAT($colSql, '$format')", $bindings];
    }

    protected function pgDateSql(string $col, string $period, string $tz): array
    {
        $bindings = [];
        if ($tz && $tz !== 'UTC') {
            $colSql = "($col at time zone 'UTC' at time zone ?)";
            $bindings[] = $tz;
        } else {
            $colSql = $col;
        }

        $format = match ($period) {
            'year'  => 'YYYY',
            'month' => 'YYYY-MM',
            'day'   => 'YYYY-MM-DD',
            'hour'  => 'YYYY-MM-DD HH24:00',
            default => 'YYYY-MM-DD'
        };
        
        return ["TO_CHAR($colSql, '$format')", $bindings];
    }

    protected function sqliteDateSql(string $col, string $period): string
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