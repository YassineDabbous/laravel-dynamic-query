<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasDynamicFilter {
    use HasDynamicCore;
    use InteractsWithSmartJoins;
 
    /**
     * Allowed filters with their operators.
     * Keys can be a column, a relation column (dot notation), or a named scope.
     * 
     * Example:
     *     return [
     *          'with_trashed'          => null,                                // equivalente to ->withTrashed() scope
     *          'name'                => null,                                // "name" column accept all operators.
     *          'price'                 => ['=', '!=', '<', '<=', '>', '>='],   // "price" column accept 6 comparison operators
     *          'user.email'            => '=',                                 // Auto-joins 'users' table
     *          'posts.title'           => 'like%',
     *     ];
     */
    public function dynamicFilters(): array {
        return [];
    }

    /**
     * Apply dynamic filtering based on URL parameters or provided array.
     * Supports standard operators, complex JSON/Fulltext operators, and named scopes.
     * 
     * Resolution order:
     * 1. Check if key matches an allowed column (via dynamicColumns()) or relation (dot notation).
     * 2. If not a column, try to call a named scope.
     * 3. Apply standard or complex operator logic.
     * 
     * @param Builder $q
     * @param array $operators Specific operator overrides per field
     * @param array $allowed   Whitelist of allowed filters (defaults to dynamicFilters())
     * @param array $ignore    Filters to exclude
     * @param array $input     Optional input data (defaults to request()->all())
     * @return Builder
     */
    public function scopeDynamicFilter(Builder $q, ?array $input = [], ?array $allowed = null, ?array $ignore = null, ?array $operators = null): Builder {
        $input = $this->resolveDynamicInput($input ?? []);
        $allowed ??= [];
        $ignore ??= [];
        $operators ??= [];

        // Config: Params
        $pLogic     = config('dynamic-query.params.logic', '_logic');
        $pClause    = config('dynamic-query.params.clause', '_clause');
        $pClauses   = config('dynamic-query.params.clauses', '_clauses');
        $pOperators = config('dynamic-query.params.operators', '_operators');

        // Parse Inputs
        $logic = ($input[$pLogic] ?? 'and') === 'or' ? 'or' : 'and';
        $defaultClause = ($input[$pClause] ?? 'where') === 'having' ? 'having' : 'where';
        $operators = count($operators) ? $this->toAssociative($operators) : ($input[$pOperators] ?? []);
        $clausesInput = $input[$pClauses] ?? [];

        // Validate Operators Early
        if (is_array($operators)) {
            $validOps = $this->allValidOperators();
            $operators = array_filter($operators, fn($op) => in_array($op, $validOps));
        }

        // Prepare Filters List
        $filters = $this->normalizeAssociativeArray(count($allowed) ? $allowed : $this->dynamicFilters());
        $filters = array_filter($filters, fn($k) => !in_array($k, $ignore), ARRAY_FILTER_USE_KEY);

        // Strict Filtering
        if (config('dynamic-query.settings.strict_filtering', true) && empty($allowed)) {
             $validKeys = array_keys($filters);
             $input = array_filter($input, function($k) use ($validKeys) {
                 $baseKey = str_starts_with($k, '!') ? substr($k, 1) : $k;
                 $baseColumn = str_contains($baseKey, '->') ? explode('->', $baseKey)[0] : $baseKey;
                 return in_array($baseColumn, $validKeys);
             }, ARRAY_FILTER_USE_KEY);
        }


        foreach ($filters as $filterKey => $ops) {
            $matchingInputKeys = array_filter(array_keys($input), function($k) use ($filterKey) {
                $base = str_starts_with($k, '!') ? substr($k, 1) : $k;
                return $base === $filterKey || str_starts_with($base, $filterKey . '->');
            });

            foreach ($matchingInputKeys as $inputKey) {
                $not = str_starts_with($inputKey, '!');
                $fullFilterPath = $not ? substr($inputKey, 1) : $inputKey;
                $value = $input[$inputKey];
                
                // Determine Clause (Where vs Having)
                $clause = $clausesInput[$inputKey] ?? $defaultClause;
                
                // Determine Operator
                $op = $operators[$inputKey] ?? ($operators[$fullFilterPath] ?? null);
                if(is_array($ops) && count($ops) && !in_array($op, $ops)){
                    $op = $ops[0] ?? '=';
                }
                $op ??= '=';

                if (str_starts_with($op, '!')) {
                    $not = !$not;
                    $op = substr($op, 1);
                }
                $operator = $op;

                if ($op === '=' && is_string($value) && preg_match('/^([<>!=]{1,2})(.+)$/', $value, $matches)) {
                    $potentialOp = $matches[1];
                    if (in_array($potentialOp, $this->allValidOperators())) {
                        $operator = $potentialOp;
                        $value = $matches[2];
                    }
                }
                
                $processedValue = $value;
                if(str_contains($operator, '%')){
                    $normOp = str_replace('!', '', $op);
                    $operator = str_replace('%', '', $normOp);
                    switch ($normOp) {
                        case '%like': $processedValue = "%$value"; break;
                        case 'like%': $processedValue = "$value%"; break;
                        case '%like%': $processedValue = "%$value%"; break;
                    }
                }

                $q = $this->applyDynamicFilter($q, $fullFilterPath, $operator, $processedValue, $logic, $not, $clause);
            }
        }

        return $q;
    }

    protected function allValidOperators(): array
    {
        return [
            // Standard
            '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
            '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
            'like', 'like binary', 'not like', 'ilike',
            'rlike', 'not rlike', 'regexp', 'not regexp',
            // Wildcards (with ! prefix variants)
            '%like', 'like%', '%like%',
            '!%like', '!like%', '!%like%',
            '!like', '!=',
            // Complex
            'full_text', 'in', 'between', 'null',
            'json_contains', 'json_contains_key', 'json_overlaps', 'json_length',
            'has',
            // Negated complex
            '!in', '!between', '!null', '!has',
            '!json_contains', '!json_contains_key', '!json_overlaps',
        ];
    }


    /**
     * Internal method to apply a single filter clause.
     * Handles priority between named scopes and database columns.
     * 
     * Resolution order:
     * 1. Check if key matches an allowed column (via dynamicColumns()) or relation (dot notation).
     * 2. If not a column, try to call a named scope.
     * 3. Apply standard or complex operator logic.
     * 
     * @param Builder $q
     * @param string $key
     * @param string $operator
     * @param mixed $value
     * @param string $logic 'and' or 'or'
     * @param bool $not Whether to negate the filter
     * @param string $clause 'where' or 'having'
     */
    protected function applyDynamicFilter(Builder $q, string $key, string $operator, mixed $value, string $logic = 'and', bool $not = false, string $clause = 'where'): Builder {
        if ($this->hasDynamicScope(Str::camel($key))) {
            return $this->callDynamicScope(Str::camel($key), [$q, $value, $operator, $logic, $not, $clause]);
        }

        $isRelation = in_array($operator, ['has', 'exists']);
        if (!$isRelation && str_contains($key, '->')) {
            $parts = explode('->', $key);
            $basePart = array_shift($parts);
            $qualifiedBase = $this->dynamicQualifyColumn($q, $basePart);
            $qualifiedKey = $qualifiedBase . '->' . implode('->', $parts);
        } else {
            $qualifiedKey = $isRelation ? $key : $this->dynamicQualifyColumn($q, $key);
        }
        $isJson = !$isRelation && str_contains($key, '->');

        $standardOperators = ['=', '<', '>', '<=', '>=', '<>', '!=', '<=>', '&', '|', '^', '<<', '>>', '&~', 'is', 'is not', 'like', '!like', '%like', 'like%', '%like%', 'like binary', 'not like', 'ilike', 'rlike', 'not rlike', 'regexp', 'not regexp'];

        if($clause === 'having'){
            $qualifiedKey = $key;
            if(in_array($operator, $standardOperators)){
                if ($not) { $q->havingRaw("NOT ($qualifiedKey $operator ?)", [$value], $logic); } else { $q->having($qualifiedKey, $operator, $value, $logic); }
                return $q;
            }
            match ($operator) {
                'in' => $q->havingRaw(($not ? 'NOT ' : '') . "$qualifiedKey IN (" . implode(',', array_fill(0, count((array)$value), '?')) . ")", (array) $value, $logic),
                'between' => $q->havingBetween($qualifiedKey, (array) $value, $logic, $not),
                'null' => $q->havingNull($qualifiedKey, $logic, $not),
                default => $q->having($qualifiedKey, '=', $value, $logic),
            };
            return $q;
        }

        $datePresets = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'this_year', 'last_year', 'last_7_days', 'last_30_days', 'ytd', 'qtd', 'mtd'];
        if (is_string($value) && in_array(strtolower($value), $datePresets)) {
            if (method_exists($this, 'applyDatePreset')) {
                return $this->applyDatePreset($q, $qualifiedKey, strtolower($value), $operator, $logic, $not);
            }
        }

        if(in_array($operator, $standardOperators)){
            if (is_array($value) && $operator === '=') { $q->whereIn($qualifiedKey, $value, $logic, $not); return $q; }
            $isStringBool = is_string($value) && in_array(strtolower($value), ['true', 'false', '1', '0'], true);
            if ($isStringBool) { $value = filter_var($value, FILTER_VALIDATE_BOOLEAN); }

            if ($isJson && $q->getConnection()->getDriverName() === 'sqlite') {
                $rawKey = is_string($qualifiedKey) ? $qualifiedKey : (string)$qualifiedKey;
                if (str_contains($rawKey, '->')) {
                    [$col, $path] = explode('->', $rawKey, 2);
                    $path = '$' . (str_starts_with($path, '$') ? '' : '.') . str_replace('->', '.', $path);
                    $expression = "json_extract($col, '$path')";
                    if ($not) { return $q->whereRaw("NOT ($expression $operator ?)", [$value], $logic); } else { return $q->whereRaw("$expression $operator ?", [$value], $logic); }
                }
            }

            if($not){ $q->whereNot($qualifiedKey, $operator, $value, $logic); } else { $q->where($qualifiedKey, $operator, $value, $logic); }
            return $q;
        }
        
        $q = match ($operator) {
            'full_text' => $not ? $q->whereNot(fn($query) => $query->whereFullText($qualifiedKey, $value), $logic) : $q->whereFullText($qualifiedKey, $value, [], $logic),
            'in' => $q->whereIn($qualifiedKey, $value, $logic, $not),
            'between' => $q->whereBetween($qualifiedKey, $value, $logic, $not),
            'null' => $q->whereNull($qualifiedKey, $logic, $not),
            'json_contains' => $q->whereJsonContains($qualifiedKey, $value, $logic, $not),
            'json_contains_key' => $q->whereJsonContainsKey($qualifiedKey, $logic, $not),
            'json_overlaps' => $q->whereJsonOverlaps($qualifiedKey, $value, $logic, $not),
            'json_length' => $not ? $q->whereNot(fn($query) => $query->whereJsonLength($qualifiedKey, $operator === 'json_length' ? '=' : $operator, $value), $logic) : $q->whereJsonLength($qualifiedKey, $operator === 'json_length' ? '=' : $operator, $value, $logic),
            'has' => (function() use ($q, $qualifiedKey, $not, $logic) {
                $method = $not ? 'whereDoesntHave' : 'whereHas';
                if ($logic === 'or') { $method = 'or' . ucfirst($method); }
                return $q->$method($qualifiedKey);
            })(),
            'exists' => (function() use ($q, $qualifiedKey, $not, $logic) {
                $method = $not ? 'whereDoesntHave' : 'whereHas';
                if ($logic === 'or') { $method = 'or' . ucfirst($method); }
                return $q->$method($qualifiedKey);
            })(),
            default => $q,
        };
        return $q;
    }
}