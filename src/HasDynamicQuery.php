<?php

namespace YassineDabbous\DynamicQuery;

trait HasDynamicQuery{
    
    use HasDynamicFields, HasDynamicFilter, HasDynamicSort, HasDynamicSort, HasDynamicGroup, HasDynamicStats;

    /** One method for all scopes. */
    public function scopeDynamicQuery(): mixed{
        return $this->dynamicSelect()->dynamicFilter()->dynamicOrderBy()->dynamicGroupBy();
    }
    
    public function scopeDynamicAPI(): mixed{
        $result = $this->dynamicSelect()->dynamicFilter()->dynamicOrderBy()->dynamicGroupBy()->dynamicPaginate();
        $result->dynamicAppend();
        return $result;
    }
}
