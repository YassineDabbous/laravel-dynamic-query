<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

trait RelationsFinder
{
    /** @var array<string, array> Cache for reflection results */
    protected static array $__dynamicQueryRelationCache = [];


    /** 
     * Guess relations from methods
     * @return array<string>
     */
    protected function guessDynamicRelations(): array
    {
        $class = static::class;
        if (isset(static::$__dynamicQueryRelationCache[$class])) {
            return static::$__dynamicQueryRelationCache[$class];
        }

        $reflection = new ReflectionClass($this);

        $result = Collection::make($reflection->getMethods())
            ->filter(fn (ReflectionMethod $method) => $this->hasReturnType($method, Relation::class))
            ->mapWithKeys(fn (ReflectionMethod $method) => [$method->getName() => $this->guessRelationColumns($method)])
            ->toArray();

        return static::$__dynamicQueryRelationCache[$class] = $result;
    }



    protected function hasReturnType(ReflectionMethod $method, string $class) : bool
    {
        if(!$method->isPublic() || $method->isStatic() || $method->getNumberOfParameters() !== 0){
            return false;
        }

        if (is_a($method->getReturnType(), ReflectionNamedType::class)) {
            $returnType = $method->getReturnType()->getName();

            return is_a($returnType, $class, true);
        }

        if (is_a($method->getReturnType(), ReflectionUnionType::class)) {
            foreach ($method->getReturnType()->getTypes() as $type) {
                $returnType = $type->getName();

                if (is_a($returnType, $class, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    
    protected function guessRelationColumns(ReflectionMethod $method) 
    {
        $relation = $method->invoke($this);
        return match (true) {
            is_a($relation, MorphOne::class) =>  $relation->getLocalKeyName(),
            is_a($relation, MorphMany::class) => $relation->getLocalKeyName(),
            is_a($relation, MorphTo::class) =>     [$relation->getMorphType(), $relation->getForeignKeyName()],
            is_a($relation, MorphToMany::class) => [$relation->getMorphType(), $relation->getForeignKeyName()],
            is_a($relation, HasOne::class) => $relation->getLocalKeyName(),
            is_a($relation, HasMany::class) => $relation->getLocalKeyName(),
            is_a($relation, HasOneThrough::class) => $relation->getLocalKeyName(),
            is_a($relation, HasManyThrough::class) => $relation->getLocalKeyName(),
            is_a($relation, BelongsTo::class) => $relation->getForeignKeyName(),
            is_a($relation, BelongsToMany::class) => $relation->getParentKeyName(),
            default => null,
        };
    }
}