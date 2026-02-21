<?php

namespace YassineDabbous\DynamicQuery;

trait HasDynamicCore {


    /** All values ​​will be of type "array". */
    protected function normalizeAssociativeArray($array): array{
        $array = $this->toAssociative($array);
        foreach($array as $k => $v){
            if(is_null($v)){
                $array[$k] = [];
            } else if(!is_array($v)){
                $array[$k] = [$v];
            }
        }
        return $array;
    }


    /** Convrty simple array to associative. */
    protected function toAssociative($array): array{
        $res = [];
        foreach((array) $array as $k => $v){
            if(is_numeric($k)){
                $res[$v] = null;
            } else {
                $res[$k] = $v;
            }
        }
        return $res;
    }


    /** Get recursive dependencies. */
    protected function recursiveDependencies(array $associative, array $keys): array {
        $seen = [];
        $maxIterations = 50; // Safety net
        $iterations = 0;
        $more = true;
        
        $requested = array_intersect(array_keys($associative), $keys);
        
        while ($more) {
            if (++$iterations > $maxIterations) break;
            $more = false;
            $filtered = array_intersect_key($associative, array_flip($requested));
            
            foreach ($filtered as $deps) {
                if (!$deps) continue;
                
                if (is_array($deps)) {
                    foreach ($deps as $dep) {
                        if (in_array($dep, $seen, true)) continue;
                        $seen[] = $dep;
                        $keys[] = $dep;
                        $requested[] = $dep;
                        $more = true;
                    }
                } else {
                    if (in_array($deps, $seen, true)) continue;
                    $seen[] = $deps;
                    $keys[] = $deps;
                    $requested[] = $deps;
                    $more = true;
                }
            }
        }
        
        return array_unique($keys);
    }

    /** Resolve input data from provided array or fallback to global request. */
    protected function resolveDynamicInput(array $provided = []): array {
        return !empty($provided) ? $provided : request()->all();
    }

    /** Helper to get a value from input with fallback to request and default. */
    protected function getDynamicValue(?array $input, string $key, $default = null) {
        if ($input !== null) {
            return $input[$key] ?? $default;
        }
        return request()->input($key, $default);
    }

    /** Helper for boolean input values. */
    protected function getDynamicBool(?array $input, string $key, bool $default = false): bool {
        if ($input !== null) {
            return filter_var($input[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
        }
        return request()->boolean($key, $default);
    }
}
