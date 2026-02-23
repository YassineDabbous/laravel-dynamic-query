<?php

namespace YassineDabbous\DynamicQuery\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use YassineDabbous\DynamicQuery\HasDynamicQuery;
use YassineDabbous\DynamicQuery\Contracts\DynamicQueryable;

class User extends Model implements DynamicQueryable
{
    use HasDynamicQuery;

    protected $fillable = ['name', 'email', 'profile'];

    protected $casts = [
        'profile' => 'array',
    ];

    public function posts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function dynamicColumns(): array
    {
        return ['id', 'name', 'email', 'profile', 'created_at', 'updated_at', 'posts_count'];
    }

    public function dynamicRelations(): array
    {
        return [
            'posts' => null
        ];
    }

    public function dynamicFilters(): array
    {
        return ['id', 'name', 'email', 'profile', 'created_at', 'posts'];
    }

    public function dynamicSorts(): array
    {
        return ['id', 'name', 'email', 'created_at'];
    }

    public function dynamicGroups(): array
    {
        return ['name', 'profile', 'created_at'];
    }

    public function dynamicMetrics(): array
    {
        return ['count'];
    }

    public function requiredColumns(): array
    {
        return ['id'];
    }

    public function dynamicAppends(): array
    {
        return [];
    }

    public function dynamicAggregates(): array
    {
        return [
            'posts_count' => fn($q) => $q->withCount('posts')
        ];
    }
}
