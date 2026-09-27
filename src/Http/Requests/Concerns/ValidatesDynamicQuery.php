<?php

namespace YassineDabbous\DynamicQuery\Http\Requests\Concerns;

/**
 * Shared validation rules for dynamic-query URL parameters.
 *
 * Mix into a FormRequest and merge with endpoint-specific rules:
 *
 *     public function rules(): array
 *     {
 *         return array_merge($this->dynamicQueryRules(), [
 *             'status' => ['nullable', 'string', 'max:30'],
 *         ]);
 *     }
 *
 * Parameter names follow the published `dynamic-query` config, so apps that
 * rename params keep working. Operator values are NOT enumerated here on
 * purpose — the query layer already drops unknown operators.
 */
trait ValidatesDynamicQuery
{
    /**
     * Validation rules for the generic dynamic-query parameters.
     *
     * @return array<string, mixed>
     */
    public function dynamicQueryRules(): array
    {
        $p = fn (string $key, string $fallback): string => (string) config("dynamic-query.params.{$key}", $fallback);

        return [
            // Accepts a CSV string ("id,name") or an array — max() covers both.
            $p('fields', '_fields') => ['nullable', 'max:2000'],
            $p('sort', '_sort') => ['nullable', 'max:1000'],
            $p('logic', '_logic') => ['nullable', 'in:and,or'],
            $p('operators', '_operators') => ['nullable', 'array'],
            $p('operators', '_operators').'.*' => ['nullable', 'string', 'max:30'],
            $p('clause', '_clause') => ['nullable', 'in:where,having'],
            $p('clauses', '_clauses') => ['nullable', 'array'],
            $p('clauses', '_clauses').'.*' => ['nullable', 'in:where,having'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$this->dynamicMaxPerPage()],
            $p('get_all', '_get_all') => ['nullable', 'boolean'],
            $p('limit', '_limit') => ['nullable', 'integer', 'min:1', 'max:'.$this->dynamicMaxGetAll()],
            $p('simple', '_simple') => ['nullable', 'boolean'],
            $p('group', '_group') => ['nullable', 'max:1000'],
            $p('metric', '_metric') => ['nullable', 'max:500'],
            $p('transform', '_transform') => ['nullable', 'in:cumulative,growth'],
            $p('compare', '_compare') => ['nullable', 'in:previous_period'],
            $p('compare_on', '_compare_on') => ['nullable', 'string', 'max:100'],
            $p('timezone', '_timezone') => ['nullable', 'timezone'],
            $p('cache', '_cache') => ['nullable', 'boolean'],
        ];
    }

    /**
     * Upper bound for `per_page`. Override per request when an endpoint
     * allows larger pages than the package default.
     */
    protected function dynamicMaxPerPage(): int
    {
        return (int) config('dynamic-query.defaults.max_per_page', 100);
    }

    /**
     * Upper bound for `_limit` in get-all mode. Override per request as needed.
     */
    protected function dynamicMaxGetAll(): int
    {
        return (int) config('dynamic-query.defaults.max_get_all', 1000);
    }
}
