<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\ServiceProvider;

class DynamicQueryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'dynamic-query');
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config.php' => config_path('dynamic-query.php'),
        ], 'dynamic-query-config');

        /**
         * Fetch a model instance from a morph map alias.
         * Useful for dynamic relationships via API.
         * 
         * @param string|null $default Default alias if none provided
         * @param array $whitelist Allowed aliases
         * @param array $input Optional input data
         */
        EloquentBuilder::macro('resolveDynamicModel', function(?string $default = null, array $whitelist = [], ?array $input = null){
            if (empty($whitelist) && $default === null) {
                throw new HttpResponseException(
                    response('dynamicModel requires either a $default or a $whitelist', 500)
                );
            }
            $input = !empty($input) ? $input : request()->all();
            $pModel = config('dynamic-query.params.model', '_model');
            $type = $input[$pModel] ?? $default;
            if(!$type){
                throw new HttpResponseException(response('morph alias required', 400));
            }
            if(count($whitelist) && !in_array($type, $whitelist)){
                throw new HttpResponseException(response('unauthorized morph alias', 403));
            }
            $class = Relation::getMorphedModel($type);
            if(!$class){
                throw new HttpResponseException(response('unknown morph alias', 400));
            }
            return (new $class)->newQuery();
        });

        /** @deprecated Use resolveDynamicModel instead. */
        EloquentBuilder::macro('dynamicModel', function(?string $default = null, array $whitelist = [], ?array $input = null){
            return $this->resolveDynamicModel($default, $whitelist, $input);
        });


        // Dynamic model appends
        $macro = function (?array $fields = [], ?array $ignore = [], ?array $input = null) {
            foreach ($this as $model) {
                if (method_exists($model, 'dynamicAppend')) {
                    $model->dynamicAppend($fields, $ignore, $input);
                }
            }
        };

        Collection::macro('dynamicAppend', $macro);
        EloquentCollection::macro('dynamicAppend', $macro);


        /**
         * Dynamic Pagination: supports custom per_page and _get_all toggles.
         * 
         * @param int|null $maxPerPage Ceiling for items per page
         * @param array $input Optional input data
         * @param bool|null $allowGet Enable/disable _get_all programmatic control
         */
        $macro = function (?int $maxPerPage = null, ?array $input = null, ?bool $allowGet = null, $columns = ['*'], $pageName = null, $page = null, $total = null) {
            $input = !empty($input) ? $input : request()->all();
            $pGetAll = config('dynamic-query.params.get_all', '_get_all');
            $pLimit = config('dynamic-query.params.limit', '_limit');
            $pPerPage = config('dynamic-query.params.per_page', 'per_page');
            
            // NOTE: 'page' parameter name is controlled by Laravel's Paginator::$pageName.
            // We only use our config for $pageName when explicitly set in the request.
            $pPage = config('dynamic-query.params.page', 'page');
            
            $pageName ??= $pPage;

            $allowGet ??= config('dynamic-query.defaults.allow_get_all', false);

            /** @var \Illuminate\Database\Eloquent\Builder|BaseBuilder $this  */
            if($allowGet && filter_var($input[$pGetAll] ?? false, FILTER_VALIDATE_BOOLEAN)){
                $maxGetAll = config('dynamic-query.defaults.max_get_all', 1000);
                $limit = (int) ($input[$pLimit] ?? $maxGetAll);
                if ($limit > $maxGetAll) {
                    $limit = $maxGetAll;
                }
                if ($limit > 0) {
                    $this->limit($limit);
                }
                return $this->get($columns);
            }

            $maxPerPage ??= config('dynamic-query.defaults.max_per_page', 50);
            $defaultSize = config('dynamic-query.defaults.per_page', 15);
            $size = (int) ($input[$pPerPage] ?? $defaultSize);
            if ($size <= 0) {
                $size = $defaultSize;
            }
            if ($size > $maxPerPage) {
                $size = $maxPerPage;
            }

            $pSimple = config('dynamic-query.params.simple', '_simple');
            $isSimple = filter_var($input[$pSimple] ?? false, FILTER_VALIDATE_BOOLEAN);

            $res = $isSimple 
                ? $this->simplePaginate($size, $columns, $pageName, $page)
                : $this->paginate($size, $columns, $pageName, $page, $total);

            // Handle dynamic appends and fields on the result collection
            $pAppend = config('dynamic-query.params.append', '_append');
            $pFields = config('dynamic-query.params.fields', '_fields');
            
            if (isset($input[$pAppend]) || isset($input[$pFields])) {
                $fields = isset($input[$pFields]) ? (is_array($input[$pFields]) ? $input[$pFields] : explode(',', $input[$pFields])) : [];
                $appends = isset($input[$pAppend]) ? (is_array($input[$pAppend]) ? $input[$pAppend] : explode(',', $input[$pAppend])) : [];
                
                $res->getCollection()->transform(function($model) use ($appends, $fields, $input) {
                    if (method_exists($model, 'dynamicAppend')) {
                        $model->dynamicAppend(array_merge($appends, $fields), [], $input);
                    }
                    return $model;
                });
            } elseif (array_is_list($input) && !empty($input)) {
                // Indexed array of field names passed directly as $input
                $res->getCollection()->transform(function($model) use ($input) {
                    if (method_exists($model, 'dynamicAppend')) {
                        $model->dynamicAppend($input, [], $input);
                    }
                    return $model;
                });
            }

            return $res;
        };

        EloquentBuilder::macro('dynamicPaginate', $macro);
        BaseBuilder::macro('dynamicPaginate', $macro);
        BelongsToMany::macro('dynamicPaginate', $macro);
        HasManyThrough::macro('dynamicPaginate', $macro);


        
    }
}
