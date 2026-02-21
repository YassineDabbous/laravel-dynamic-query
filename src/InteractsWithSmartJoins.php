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
    protected function dynamicJoinRelation(Builder $query, string $relationName)
    {
        // Prevent duplicate joins
        $joins = $query->getQuery()->joins ?? [];
        foreach ($joins as $join) {
            if ($join->table === $this->getRelationTableName($relationName)) {
                return; 
            }
        }

        if (!method_exists($this, $relationName)) {
            return;
        }

        $relation = $this->{$relationName}();
        
        // Only handle BelongsTo and HasOne/Many for BI Joins roughly
        // Morph relations are too complex for auto-joining in this context usually
        if (is_a($relation, BelongsTo::class)) {
            $relatedTable = $relation->getRelated()->getTable();
            $fk = $relation->getForeignKeyName();
            $ownerKey = $relation->getOwnerKeyName();
            $localTable = $this->getTable();
            
            $query->leftJoin($relatedTable, "$localTable.$fk", '=', "$relatedTable.$ownerKey");
        } 
        elseif (is_a($relation, HasOneOrMany::class)) {
            $relatedTable = $relation->getRelated()->getTable();
            $fk = $relation->getForeignKeyName();
            $localKey = $relation->getLocalKeyName();
            $localTable = $this->getTable();

            $query->leftJoin($relatedTable, "$localTable.$localKey", '=', "$relatedTable.$fk");
        }
    }

    protected function getRelationTableName($relationName)
    {
        return $this->{$relationName}()->getRelated()->getTable();
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
            return $this->getTable() . '.' . $field;
        }

        [$relation, $column] = explode('.', $field, 2);

        // Recursive support could go here, but let's stick to depth-1 for stability
        if (method_exists($this, $relation)) {
            $this->dynamicJoinRelation($query, $relation);
            $tableName = $this->getRelationTableName($relation);
            return "{$tableName}.{$column}";
        }

        return $field;
    }
}