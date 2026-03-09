<?php

namespace YassineDabbous\DynamicQuery;

trait HasDynamicCore {

    public function hasDynamicScope($scope): bool {
        $found = method_exists($this, 'scope' . ucfirst($scope));
        // var_dump("hasDynamicScope check for $scope: " . ($found ? 'YES' : 'NO'));
        return $found;
    }

    public function callDynamicScope($scope, array $parameters = []) {
        // var_dump("callDynamicScope calling $scope");
        return $this->{'scope' . ucfirst($scope)}(...$parameters);
    }


    /** All values ​​will be of type "array". */
    protected function normalizeAssociativeArray(array $array): array{
        return DynamicQueryHelper::normalizeAssociativeArray($array);
    }


    /** Convert simple array to associative. */
    protected function toAssociative(array $array): array{
        return DynamicQueryHelper::toAssociative($array);
    }


    /** Get recursive dependencies. */
    protected function recursiveDependencies(array $associative, array $keys): array {
        return DynamicQueryHelper::recursiveDependencies($associative, $keys);
    }

    protected function resolveDynamicInput(?array $provided = null): array {
        /** @var array $input */
        $input = ($provided !== null && count($provided) > 0) ? $provided : request()->all();
        return $input;
    }

    /** Helper to get a value from input with fallback to request and default. */
    protected function getDynamicValue(?array $input, string $key, mixed $default = null): mixed {
        if ($input !== null) {
            return data_get($input, $key, $default);
        }
        return request()->get($key, $default);
    }

    /** Helper for boolean input values. */
    protected function getDynamicBool(?array $input, string $key, bool $default = false): bool {
        if ($input !== null) {
            return filter_var(data_get($input, $key, $default), FILTER_VALIDATE_BOOLEAN);
        }
        return filter_var(request()->has($key) ? request()->input($key) : $default, FILTER_VALIDATE_BOOLEAN);
    }

    protected function sanitizeAlias(string $alias): string
    {
        return DynamicQueryHelper::sanitizeAlias($alias);
    }

    protected function isJsonField(string $field): bool
    {
        return str_contains($field, '->');
    }
}
