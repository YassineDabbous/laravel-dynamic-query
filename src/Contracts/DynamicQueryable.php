<?php

namespace YassineDabbous\DynamicQuery\Contracts;

interface DynamicQueryable
{
    public function dynamicColumns(): array;
    public function dynamicRelations(): array;
    public function dynamicAppends(): array;
    public function dynamicAggregates(): array;
    public function dynamicFilters(): array;
    public function dynamicSorts(): array;
    public function dynamicGroups(): array;
    public function dynamicMetrics(): array;
    public function requiredColumns(): array;
}
