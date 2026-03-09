<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasDynamicFields{

    use HasDynamicCore;
    use RelationsFinder;

    protected $__dynamicQueryDeepFields = [];


    /**
     * All selectable table columns
     */
    public function dynamicColumns(): array {
        return [];
    }

    /**
     * Columns that must always be selected for the model to function correctly.
     * These are force-included in every dynamic query.
     *
     * Example:
     *   return ['id', 'owner_id', 'status'];
     */
    public function requiredColumns(): array
    {
        return [$this->getKeyName()];
    }

    /**
     * Accessable Model Relations with their dependencies.
     * Example:
     *     return [
     *       'user' => 'user_id',                                        // "user" relation depends on 'user_id' column
     *       'commentable' => ['commentable_type', 'commentable_id'],    // "morphable" relation depends on 'morphable_type' and 'morphable_id' columns
     *       'replies' => null,                                          // "replies" relation doesn't has dependencies
     *     ];
     */
    public function dynamicRelations(): array{
        if (config('dynamic-query.settings.relation_guess', true)) {
            return $this->guessDynamicRelations();
        }
        return [];
    }


    /**
     * Whitelisted appends. Returns [] by default for security.
     * 
     * Example:
     *   return [
     *        'status_name'     => 'status',                            // "status_name" depends on 'status' relation
     *        'full_name'       => ['first_name', 'last_name'],         // "full_name" depends on 'first_name' and 'last_name' columns
     *        'custom_key',                                             // "custom_key" doesn't has dependencies
     *   ];
     * 
     * @return array
     */
    public function dynamicAppends(): array{
        return [];
    }


    /**
     * Model Aggregates as Closures.
     * Values can be: Closure, named scope, NULL.
     * Keys with an empty value will be treated as a named scope.
     * 
     * Example:
     *      return [
     *          'custom'                => null,                    // equal to ->custom() scope
     *          'another_custom'        => 'named_scope',           // equal to ->namedScope() scope
     *          'employees_count'       => fn($q) => $q->withCount('employees'),
     *          'employees_sum_salary'  => fn($q) => $q->withSum('employees', 'salary'),
     *      ];
     */
    public function dynamicAggregates(): array{
        return [];
    }


    /** Append only requests fields. */
    public function dynamicAppend(?array $fields = [], ?array $ignore = [], ?array $input = null): void {
        $input = $this->resolveDynamicInput($input);
        $fields ??= [];
        $ignore ??= [];
        $parsed = $this->parseFields($fields, $input);
        $list = array_keys($parsed['fields']);
        $list = array_diff($list, $ignore);

        if(count($list)){
            // MERGE with existing visible (set by scopeDynamicSelect) instead of overwriting
            $existing = $this->getVisible();
            if (!empty($existing)) {
                $merged = array_unique(array_merge($existing, $list));
                // If a JSON path is requested, make the base column visible too
                foreach ($list as $f) {
                    if (str_contains($f, '->')) {
                        $merged[] = explode('->', $f)[0];
                    }
                }
                $this->setVisible(array_unique($merged));
            } else {
                $merged = $list;
                foreach ($list as $f) {
                    if (str_contains($f, '->')) {
                        $merged[] = explode('->', $f)[0];
                    }
                }
                $this->setVisible(array_unique($merged));
            }

            $dynamicAppends = $this->toAssociative($this->dynamicAppends());
            $columns = array_intersect(array_keys($dynamicAppends), $list);
            if(count($columns)){
                $this->appends = array_unique(array_merge($this->appends, $columns));
            }

            // add appends to child relations
            foreach ($parsed['deepFields'] as $key => $deepFs) {
                if(is_a($this->{$key}, EloquentCollection::class)){
                    foreach ($this->{$key} as $relation) {
                        $relation->dynamicAppend($deepFs, [], $input);
                    }
                } 
                else if(is_a($this->{$key}, Model::class)){
                    $this->{$key}?->dynamicAppend($deepFs, [], $input);
                }
            }
        }
    }

    /**
     * Clean the model response to only include requested fields in toArray() / toJson().
     * This uses setVisible() internally.
     * 
     * @param Model|null $model Model instance to clean (defaults to $this)
     * @param array $fields Fields/Columns to keep
     * @return Model
     */
    protected function cleanResponse(?Model $model = null, array $fields = []): Model
    {
        $model ??= $this;
        if (!empty($fields) && !in_array('*', $fields)) {
             $model->setVisible($fields);
        }
        return $model;
    }


    /**
     * Apply dynamic field selection, relation loading, and aggregates.
     * 
     * Resolution order:
     * 1. Parse requested fields from URL (_fields) or $fields array.
     * 2. Resolve append dependencies (columns needed by accessors).
     * 3. Resolve relation dependencies (foreign keys needed for loading).
     * 4. Eager-load relations with optional deep field filtering.
     * 5. Apply column selection (intersection with dynamicColumns() whitelist).
     * 6. Call aggregate scopes if requested.
     * 
     * @param Builder $q
     * @param array $fields Specific field whitelist (overrides URL)
     * @param array $ignore Fields to exclude
     * @param array $input  Optional input data (defaults to request()->all())
     * @return Builder
     */
    public function scopeDynamicSelect(Builder $q, ?array $input = [], ?array $fields = null, ?array $ignore = null): Builder {
        $input = $this->resolveDynamicInput($input ?? []);
        $fields ??= [];
        $ignore ??= [];
        $parsed = $this->parseFields($fields, $input);
        $list = array_diff(array_keys($parsed['fields']), $ignore);
        $this->__dynamicQueryDeepFields = $parsed['deepFields'];
        
        if(count($list)==0){
            return $q;
        }

        if (config('dynamic-query.settings.clean_response', true)) {
             $q->afterQuery(function($models) use ($list) {
                 foreach ($models as $model) {
                     $model->setVisible($list);
                 }
             });
        }
        
        $dynamicAppends = $this->toAssociative($this->dynamicAppends());
        $dynamicAppendsNames = array_keys($dynamicAppends);

        
        // add Appends dependencies to the list.
        if(count($dynamicAppendsNames)){
            $list = $this->recursiveDependencies($dynamicAppends, $list);
        }
        

        $dynamicRelations = $this->toAssociative($this->dynamicRelations());
        $dynamicRelationsNames = array_keys($dynamicRelations);

        
        // add Relations dependencies to the list.
        if(count($dynamicRelationsNames)){
            $list = $this->recursiveDependencies($dynamicRelations, $list);
        }


        $requestedRelations = array_intersect($dynamicRelationsNames, $list);
        if(count($requestedRelations)){
            $uniqueRelations = array_unique($requestedRelations);
            foreach ($uniqueRelations as $relationName) {
                if(array_key_exists($relationName, $this->__dynamicQueryDeepFields)) {
                    // Get the foreign key(s) for the current relationship.
                    $relationDependencies = (array) ($dynamicRelations[$relationName] ?? []);
                    // Get the fields the user requested for this deep relation.
                    $deepFields = $this->__dynamicQueryDeepFields[$relationName];
                    
                    $fieldsForRelation = array_unique(array_merge($deepFields, $relationDependencies));

                    $q->with($relationName, fn($rq) => $rq->dynamicSelect($fieldsForRelation, [], $input));
                } else {
                    $q->with($relationName);
                }
            }
        }
        
        $dynamicAggregates = $this->toAssociative($this->dynamicAggregates());
        $dynamicAggregatesNames = array_keys($dynamicAggregates);

        if(!in_array('*', $list)){
            $selectableColumns = $this->dynamicColumns();
            $requiredColumns = $this->requiredColumns();

            if(count($selectableColumns)){
                 // This prevents trying to select relation names like "children" as columns.
                $requestedColumns = array_filter($list, function($field) use ($selectableColumns) {
                    $baseColumn = str_contains($field, '->') ? explode('->', $field)[0] : $field;
                    return in_array($baseColumn, $selectableColumns);
                });
            } else {
                $nonColumnFields = [
                    ...$dynamicRelationsNames,
                    ...$dynamicAggregatesNames,
                    ...$dynamicAppendsNames
                ];
                $requestedColumns = array_diff($list, $nonColumnFields);
            }

            $finalColumns = array_unique(array_merge($requiredColumns, $requestedColumns));
            
            if(count($finalColumns)){
                 $q->select(array_unique($finalColumns));
            } else {
                // If no columns are explicitly selected after filtering, select at least the primary key
                // to avoid "select *" which can happen if $finalColumns is empty.
                $q->select($this->getTable() . '.' . $this->getKeyName());
            }
        }


        // *Aggregates must be called after selection
        if(count($dynamicAggregatesNames)){
            $requestedAggregates = array_intersect($dynamicAggregatesNames, $list);
            foreach ($requestedAggregates as $key) {
                $value = $dynamicAggregates[$key];
                if(is_null($value)){
                    if($this->hasNamedScope(Str::camel($key))){ 
                        $q = $this->callNamedScope(Str::camel($key), [$q]);
                    }
                    continue;
                }
                if(is_string($value)){
                    if($this->hasNamedScope(Str::camel($value))){ 
                        $q = $this->callNamedScope(Str::camel($value), [$q]);
                    }
                    continue;
                }
                if(is_a($value, \Closure::class)){
                    $value($q);
                }
            }
        }
        return $q;
    }

    /**
     * Transform nested selection string (id,posts:id|title) to nested associative array.
     * Also extracts deep fields.
     * 
     * @param array $fields
     * @param array $input
     * @return array{fields: array, deepFields: array}
     */
    protected function parseFields(array $fields = [], array $input = []): array {
        if(count($fields)) {
            $list = $fields;
        } else {
            $pFields = config('dynamic-query.params.fields', '_fields');
            $requested = $input[$pFields] ?? [];
            $list = is_array($requested) ? $requested : explode(',', $requested);
        }

        $list = array_filter(array_map('trim', $list));

        // Whitelist filtering
        $selectableColumns = $this->dynamicColumns();
        $dynamicRelations = array_keys($this->toAssociative($this->dynamicRelations()));
        $dynamicAppends = array_keys($this->toAssociative($this->dynamicAppends()));
        $dynamicAggregates = array_keys($this->toAssociative($this->dynamicAggregates()));

        $allowed = array_merge($selectableColumns, $dynamicRelations, $dynamicAppends, $dynamicAggregates, ['*']);

        $list = array_filter($list, function($field) use ($allowed) {
             // Handle JSON paths: base column must be in allowed list
             $base = str_contains($field, '->') ? explode('->', $field)[0] : $field;
             // Handle relation subfields prefix: relation name must be in allowed list
             $relationName = str_contains($base, ':') ? explode(':', $base)[0] : $base;

             return in_array($relationName, $allowed);
        });

        $res = [];
        $deepFields = [];

        foreach($list as $field){
            if(str_contains($field, ':')){
                [$relation, $subFields] = explode(':', $field);
                $res[$relation] = null; // Mark relation as requested
                $deepFields[$relation] = explode('|', $subFields);
            } else {
                $res[] = $field;
            }
        }

        return [
            'fields' => $this->normalizeAssociativeArray($res),
            'deepFields' => $deepFields,
        ];
    }
 
}
