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


    public function scopeDynamicFilter(Builder $q, array $operators = [], array $allowed = [], array $ignore = []): Builder {

        /** @var \Illuminate\Http\Request $request */
        $request = request();

        // Config: Params
        $pLogic     = config('dynamic-query.params.logic', '_logic');
        $pClause    = config('dynamic-query.params.clause', '_clause');    // Global default clause
        $pClauses   = config('dynamic-query.params.clauses', '_clauses');  // Per-field clause overrides
        $pOperators = config('dynamic-query.params.operators', '_operators');

        // Parse Inputs
        $logic = $request->input($pLogic, 'and') === 'or' ? 'or' : 'and';
        $defaultClause = $request->input($pClause, 'where') === 'having' ? 'having' : 'where';
        
        $operators = count($operators) ? $operators : $request->input($pOperators, []);
        $clausesInput = $request->input($pClauses, []);

        // Prepare Filters List
        $filters = $this->fixArray(count($allowed) ? $allowed : $this->dynamicFilters());
        $filters = array_filter($filters, fn($k) => !in_array($k, $ignore), ARRAY_FILTER_USE_KEY);

        foreach ($filters as $key => $ops) {
            if($request->has($key)){
                // Determine Clause (Where vs Having)
                $clause = $clausesInput[$key] ?? $defaultClause;
                
                // Determine Operator
                $op = $operators[$key] ?? null;
                if(count($ops) && !in_array($op, $ops)){
                    $op = $ops[0] ?? '=';
                }
                $op ??= '='; // if null

                // Parse Operator Modifiers (! and %)
                $not = str_starts_with($op, '!');
                $operator = str_replace('!', '', $op);
                
                if(str_contains($operator, '%')){
                    $value = $request->{$key};
                    $value = match($operator){
                        '%like' => "%$value",
                        'like%' => "$value%",
                        default => $value
                    };     
                    $operator = str_replace('%', '', $operator);
                    $requestValue = $value;
                } else {
                    $requestValue = $request->{$key};
                }

                $this->applyDynamicFilter($q, $key, $operator, $requestValue, $logic, $not, $clause);
            }
        }

        return $q;
    }


    public function applyDynamicFilter(Builder $q, string $key, string $operator, $value, string $logic = 'and', bool $not = false, string $clause = 'where'){
        
        // 1. Handle Named Scopes first
        // We use the raw key here (e.g., 'with_trashed' or 'user_active')
        if($this->hasNamedScope(Str::camel($key))){ 
            $this->callNamedScope(Str::camel($key), [$q, $value, $operator, $logic, $not, $clause]);
            return;
        }

        // 2. Resolve Smart Joins & Qualify Column
        // If key is 'user.email', this joins 'users' and returns 'users.email'.
        // If key is 'status', it returns 'orders.status' (qualified with main table).
        // This is provided by InteractsWithSmartJoins trait.
        $qualifiedKey = $this->dynamicQualifyColumn($q, $key);


        $standardOperators = [
            '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
            '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
            'like', '!like', '%like', 'like%',
            'like binary', 'not like', 'ilike',
            'rlike', 'not rlike', 'regexp', 'not regexp',
        ];

        // apply HAVING clause
        // We use qualifiedKey here to avoid ambiguity in joins
        if($clause == 'having'){
            if(in_array($operator, $standardOperators)){
                $q->having($qualifiedKey, $operator, $value, $logic);
                return;
            }
            match ($operator) {
                'in' => $q->having($qualifiedKey, $operator, $value, $logic),
                'between' => $q->whereBetween($qualifiedKey, $value, $logic, $not),
                'null' => $q->havingNull($qualifiedKey, $logic, $not),
            };
            return;
        }

        
        // apply WHERE clause
        if(in_array($operator, $standardOperators)){
            if($value === 'true'){
                $value = true;
            }
            if($value === 'false'){
                $value = false;
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
            'full_text' => $q->whereFullText($qualifiedKey, $value, [], $logic),
            'in' => $q->whereIn($qualifiedKey, $value, $logic, $not),
            'between' => $q->whereBetween($qualifiedKey, $value, $logic, $not),
            'null' => $q->whereNull($qualifiedKey, $logic, $not),
            'json_contains' => $q->whereJsonContains($qualifiedKey, $value, $logic, $not),
            'json_contains_key' => $q->whereJsonContainsKey($qualifiedKey, $logic, $not),
            'json_overlaps' => $q->whereJsonOverlaps($qualifiedKey, $value, $logic, $not),
            'json_length' => $q->havingJsonLength($qualifiedKey, '=', $value, $logic),
            'has' => $q->has($qualifiedKey, $not ? '<' : '>=', 1, $logic),
        };
    }
}