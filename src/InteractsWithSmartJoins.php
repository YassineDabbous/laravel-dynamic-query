<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;

trait InteractsWithSmartJoins
{
    use RelationsFinder;

    /**
     * Joins a relation if it hasn't been joined already.
     * Supports dot notation: 'posts.comments'
     * 
     * @param Builder $query
     * @param string $relationName
     */
    protected function dynamicJoinRelation(Builder $query, string $relationName): void
    {
        // Check method exists FIRST (before getRelationTableName which calls it)
        if (!method_exists($this, $relationName)) {
            return;
        }

        // Prevent duplicate joins
        $joins = $query->getQuery()->joins ?? [];
        foreach ($joins as $join) {
            if ($join->table === $this->getRelationTableName($relationName)) {
                return; 
            }
        }

        $relation = $this->{$relationName}();
        
        // Only handle BelongsTo and HasOne/Many for BI Joins roughly
        // Morph relations are too complex for auto-joining in this context usually
        if (is_a($relation, BelongsTo::class)) {
            $relatedTable = $relation->getRelated()->getTable();
            $fk = $relation->getForeignKeyName();
            $ownerKey = $relation->getOwnerKeyName();
            $localTable = $this->getTable();
            
            $query->join($relatedTable, "$localTable.$fk", '=', "$relatedTable.$ownerKey");
        } 
        elseif (is_a($relation, HasOneOrMany::class)) {
            $relatedTable = $relation->getRelated()->getTable();
            $fk = $relation->getForeignKeyName();
            $localKey = $relation->getLocalKeyName();
            $localTable = $this->getTable();

            $query->join($relatedTable, "$localTable.$localKey", '=', "$relatedTable.$fk");
        }
    }

    protected function getRelationTableName(string $relationName): ?string
    {
        try {
            $relation = $this->{$relationName}();
            if ($relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                return $relation->getRelated()->getTable();
            }
        } catch (\Throwable $e) {}
        
        return null;
    }
    
    /** 
     * Qualifies a column name, automatically joining any relations in dot notation.
     * Example: Resolve 'user.name' -> joins 'users' -> returns 'users.name' 
     * 
     * @param Builder $query
     * @param string $field
     * @return string
     */
    protected function dynamicQualifyColumn(Builder $query, string $field): string
    {
        if (!str_contains($field, '.')) {
            return method_exists($this, 'qualifyColumn') ? $this->qualifyColumn($field) : $this->getTable() . '.' . $field;
        }

        [$relation, $column] = explode('.', $field, 2);

        // Recursive support could go here, but let's stick to depth-1 for stability
        if (method_exists($this, $relation)) {
            $this->dynamicJoinRelation($query, $relation);
            $tableName = $this->getRelationTableName($relation);
            if ($tableName) {
                return "{$tableName}.{$column}";
            }
        }

        return $field;
    }
}