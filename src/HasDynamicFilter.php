<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder;

trait HasDynamicFilter{
    use HasDynamicCore;
 
    /**
     * Allowed filters with their operators.
     * Keys can be a column or a named scope, they should all be in snake_case.
     * 
     * Example:
     *     return [
     *          'with_trashed'          => null,                                // equal to ->withTrashed() scope
     *          'name'                  => null,                                // "name" column accept all operators.
     *          'id'                    => '=',                                 // "id" accept only "=".
     *          'price'                 => ['=', '!=', '<', '<=', '>', '>='],   // "price" column accept 6 comparison operators
     *     ];
     */
    public function dynamicFilters(): array {
        return [];
    }


    public function scopeDynamicFilter(Builder $q, array $operators = [], array $allowed = [], array $ignore = []): Builder {

        /** @var \Illuminate\Http\Request $request */
        $request = request();

        $logic = $request->input('_logic', 'and') === 'or' ? 'or' : 'and';
        $defaultClause = $request->input('_clause', 'where') === 'having' ? 'having' : 'where';
        // array_merge(...array_values($request->input('_operators', [])))
        $operators = count($operators) ? $operators : $request->input('_operators', []);
        $clauses = $request->input('_clauses', []);

        $filters = $this->fixArray(count($allowed) ? $allowed : $this->dynamicFilters());
        $filters = array_filter($filters, fn($k) => !in_array($k, $ignore), ARRAY_FILTER_USE_KEY);

        foreach ($filters as $key => $ops) {
            if($request->has($key)){
                $clause = $clauses[$key] ?? $defaultClause;
                $op = $operators[$key] ?? null;
                if(count($ops) && !in_array($op, $ops)){
                    $op = $ops[0] ?? '=';
                }
                $op ??= '='; // if null

                $not = str_starts_with($op, '!');
                $operator = str_replace('!', '', $op);
                
                if(str_contains($operator, '%')){
                    $value = match($operator){
                        '%like' => "%$value",
                        'like%' => "$value%",
                    };     
                    $operator = str_replace('%', '', $operator);
                }

                

                $this->applyDynamicFilter($q, $key, $operator, $request->{$key}, $logic, $not, $clause);
            }
        }

        return $q;
    }



    public function applyDynamicFilter(Builder $q, string $key, string $operator, $value, string $logic = 'and', bool $not = false, string $clause = 'where'){
        // dump($key, $operator, $value, $logic, $not, $clause);
        if($this->hasNamedScope(\Str::camel($key))){ 
            $this->callNamedScope(\Str::camel($key), [$q, $value, $operator, $logic, $not, $clause]);
            return;
        }

        $operators = [
            '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
            '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
            'like', '!like', '%like', 'like%',
            'like binary', 'not like', 'ilike',
            'rlike', 'not rlike', 'regexp', 'not regexp',
        ];

        // apply HAVING clause
        if($clause == 'having'){
            if(in_array($operator, $operators)){
                $q->having($key, $operator, $value, $logic);
                return;
            }
            match ($operator) {
                'in' => $q->having($key, $operator, $value, $logic),
                'between' => $q->whereBetween($key, $value, $logic, $not),
                'null' => $q->havingNull($key, $logic, $not),
            };
            return;
        }

        
        // apply WHERE clause
        if(in_array($operator, $operators)){
            if($not){
                $q->whereNot($key, $operator, $value, $logic);
            } else {
                $q->where($key, $operator, $value, $logic);
            }
            return;
        }
        
        match ($operator) {
            'full_text' => $q->whereFullText($key, $value, [], $logic),
            'in' => $q->whereIn($key, $value, $logic, $not),
            'between' => $q->whereBetween($key, $value, $logic, $not),
            'null' => $q->whereNull($key, $logic, $not),
            'json_contains' => $q->whereJsonContains($key, $value, $logic, $not),
            'json_contains_key' => $q->whereJsonContainsKey($key, $logic, $not),
            'json_overlaps' => $q->whereJsonOverlaps($key, $value, $logic, $not),
            'json_length' => $q->havingJsonLength($key, '=', $value, $logic),
            'has' => $q->has($key, $not ? '<' : '>=', 1, $logic),
        };
    }

 
}
