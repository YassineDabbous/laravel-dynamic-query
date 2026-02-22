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
    public function scopeDynamicFilter(Builder $q, array $operators = [], array $allowed = [], array $ignore = [], array $input = []): Builder {
        $input = $this->resolveDynamicInput($input);

        // Config: Params
        $pLogic     = config('dynamic-query.params.logic', '_logic');
        $pClause    = config('dynamic-query.params.clause', '_clause');    // Global default clause
        $pClauses   = config('dynamic-query.params.clauses', '_clauses');  // Per-field clause overrides
        $pOperators = config('dynamic-query.params.operators', '_operators');

        // Parse Inputs
        $logic = ($input[$pLogic] ?? 'and') === 'or' ? 'or' : 'and';
        $defaultClause = ($input[$pClause] ?? 'where') === 'having' ? 'having' : 'where';
        $operators = count($operators) ? $this->normalizeAssociativeArray($operators) : ($input[$pOperators] ?? []);
        $clausesInput = $input[$pClauses] ?? [];

        // Validate Operators Early
        if (is_array($operators)) {
            $validOps = $this->allValidOperators();
            $operators = array_filter($operators, fn($op) => in_array($op, $validOps));
        }

        // Prepare Filters List
        $filters = $this->normalizeAssociativeArray(count($allowed) ? $allowed : $this->dynamicFilters());
        $filters = array_filter($filters, fn($k) => !in_array($k, $ignore), ARRAY_FILTER_USE_KEY);

        // Strict Filtering: If enabled, only allow columns present in $filters
        if (config('dynamic-query.settings.strict_filtering', true) && empty($allowed)) {
             $input = array_intersect_key($input, $filters);
        }

        foreach ($filters as $key => $ops) {
            if (isset($input[$key])) {
                $value = $input[$key];
                
                // Determine Clause (Where vs Having)
                $clause = $clausesInput[$key] ?? $defaultClause;
                
                // Determine Operator
                $op = $operators[$key] ?? null;
                if(is_array($ops) && count($ops) && !in_array($op, $ops)){
                    $op = $ops[0] ?? '=';
                }
                $op ??= '='; // if null

                // Parse Operator Modifiers (! and %)
                $not = str_starts_with($op, '!');
                $operator = str_replace('!', '', $op);
                
                if(str_contains($operator, '%')){
                    $normOp = str_replace('!', '', $op);
                    $operator = str_replace('%', '', $normOp);
                    $processedValue = $value;
                    switch ($normOp) {
                        case '%like':
                            $processedValue = "%$value";
                            break;
                        case 'like%':
                            $processedValue = "$value%";
                            break;
                        case '%like%':
                            $processedValue = "%$value%";
                            break;
                    }
                } else {
                    $processedValue = $value;
                }

                $this->applyDynamicFilter($q, $key, $operator, $processedValue, $logic, $not, $clause);
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
    protected function applyDynamicFilter(Builder $q, string $key, string $operator, mixed $value, string $logic = 'and', bool $not = false, string $clause = 'where'): void
    {

        // Check if this key is a real column or dot-notation relation column
        $isColumn = in_array($key, $this->dynamicColumns())
                    || str_contains($key, '.');

        // If it's NOT a column, try Named Scopes
        if (!$isColumn && $this->hasNamedScope(Str::camel($key))) {
            $this->callNamedScope(Str::camel($key), [$q, $value, $operator, $logic, $not, $clause]);
            return;
        }

        // 3. Resolve Smart Joins & Qualify Column (only for actual columns)
        // If key is 'user.email', this joins 'users' and returns 'users.email'.
        // If key is 'status', it returns 'orders.status' (qualified with main table).
        // This is provided by InteractsWithSmartJoins trait.
        $qualifiedKey = $this->dynamicQualifyColumn($q, $key);


        $standardOperators = [
            '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
            '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
            'like', '!like', '%like', 'like%', '%like%',
            'like binary', 'not like', 'ilike',
            'rlike', 'not rlike', 'regexp', 'not regexp',
        ];

        // apply HAVING clause
        // We use qualifiedKey here to avoid ambiguity in joins
        if($clause === 'having'){
            $qualifiedKey = $key; // having usually uses alias or raw column
            if(in_array($operator, $standardOperators)){
                if ($not) {
                    $q->havingRaw("NOT ($qualifiedKey $operator ?)", [$value], $logic);
                } else {
                    $q->having($qualifiedKey, $operator, $value, $logic);
                }
                return;
            }
            match ($operator) {
                'in' => $q->havingRaw(($not ? 'NOT ' : '') . "$qualifiedKey IN (" . implode(',', array_fill(0, count((array)$value), '?')) . ")", (array) $value, $logic),
                'between' => $q->havingBetween($qualifiedKey, (array) $value, $logic, $not),
                'null' => $q->havingNull($qualifiedKey, $logic, $not),
                default => $q->having($qualifiedKey, '=', $value, $logic),
            };
            return;
        }

        
        // apply WHERE clause
        if(in_array($operator, $standardOperators)){
            // Edge case: if value is an array and operator is '=', treat as 'in'
            if (is_array($value) && $operator === '=') {
                $q->whereIn($qualifiedKey, $value, $logic, $not);
                return;
            }

            if (is_string($value) && in_array(strtolower($value), ['true', 'false', '1', '0'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            if($not){
                $q->whereNot($qualifiedKey, $operator, $value, $logic);
            } else {
                $q->where($qualifiedKey, $operator, $value, $logic);
            }
            return;
        }
        
        // Complex operators
        match ($operator) {
            'full_text' => $not 
                ? $q->whereNot(fn($query) => $query->whereFullText($qualifiedKey, $value), $logic)
                : $q->whereFullText($qualifiedKey, $value, [], $logic),
            'in' => $q->whereIn($qualifiedKey, $value, $logic, $not),
            'between' => $q->whereBetween($qualifiedKey, $value, $logic, $not),
            'null' => $q->whereNull($qualifiedKey, $logic, $not),
            'json_contains' => $q->whereJsonContains($qualifiedKey, $value, $logic, $not),
            'json_contains_key' => $q->whereJsonContainsKey($qualifiedKey, $logic, $not),
            'json_overlaps' => $q->whereJsonOverlaps($qualifiedKey, $value, $logic, $not),
            'json_length' => $not
                ? $q->whereNot(fn($query) => $query->whereJsonLength($qualifiedKey, $operator === 'json_length' ? '=' : $operator, $value), $logic)
                : $q->whereJsonLength($qualifiedKey, $operator === 'json_length' ? '=' : $operator, $value, $logic),
            'has' => (function() use ($q, $qualifiedKey, $not, $logic) {
                // Strip qualification for relation names (products.comments -> comments)
                $relation = str_contains($qualifiedKey, '.') ? last(explode('.', $qualifiedKey)) : $qualifiedKey;
                return $q->has($relation, $not ? '<' : '>=', 1, $logic);
            })(),
            default => null,
        };
    }
}