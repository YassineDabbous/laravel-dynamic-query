<?php

namespace YassineDabbous\DynamicQuery;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\ServiceProvider;

class DynamicQueryServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'dynamic-query');
        $this->publishes([
            __DIR__.'/config.php' => config_path('dynamic-query.php'),
        ], 'dynamic-query-config');

        /**
         * Fetch a model instance from a morph map alias.
         * Useful for dynamic relationships via API.
         * 
         * @param string|null $default Default alias if none provided
         * @param array $whitelist Allowed aliases
         */
        EloquentBuilder::macro('dynamicModel', function(?string $default = null, array $whitelist = []){
            if (empty($whitelist) && $default === null) {
                throw new HttpResponseException(
                    response('dynamicModel requires either a $default or a $whitelist', 500)
                );
            }
            $pModel = config('dynamic-query.params.model', '_model');
            $type = request()->input($pModel, $default);
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
            return new $class;
        });


        // Dynamic model appends
        $macro = function (array $fields = [], array $ignore = []) {
            foreach ($this as $model) {
                $model->dynamicAppend($fields, $ignore);
            }
        };

        Collection::macro('dynamicAppend', $macro);


        /**
         * Dynamic Pagination: supports custom per_page and _get_all toggles.
         * 
         * @param int|null $maxPerPage Ceiling for items per page
         * @param array $input Optional input data
         * @param bool|null $allowGet Enable/disable _get_all programmatic control
         */
        $macro = function (?int $maxPerPage = null, array $input = [], ?bool $allowGet = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null) {
            $input = !empty($input) ? $input : request()->all();
            $pGetAll = config('dynamic-query.params.get_all', '_get_all');
            $pLimit = config('dynamic-query.params.limit', '_limit');

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
            $defaultSize = config('dynamic-query.defaults.per_page', 5);
            $size = (int) ($input['per_page'] ?? $defaultSize);
            if ($size <= 0) {
                $size = $defaultSize;
            }
            if ($size > $maxPerPage) {
                $size = $maxPerPage;
            }

            $currentPage = (int) ($input[$pageName] ?? 0);
            return $currentPage == 1 ? $this->paginate($size, $columns, $pageName, $page, $total) : $this->simplePaginate($size, $columns, $pageName, $page);
        };

        EloquentBuilder::macro('dynamicPaginate', $macro);
        BaseBuilder::macro('dynamicPaginate', $macro);
        BelongsToMany::macro('dynamicPaginate', $macro);
        HasManyThrough::macro('dynamicPaginate', $macro);


        
    }
}
