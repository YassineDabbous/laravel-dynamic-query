<?php

namespace YassineDabbous\DynamicQuery\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use YassineDabbous\DynamicQuery\HasDynamicQuery;
use YassineDabbous\DynamicQuery\Contracts\DynamicQueryable;

class Post extends Model implements DynamicQueryable
{
    use HasDynamicQuery;

    protected $fillable = ['user_id', 'title', 'content', 'status', 'count', 'likes', 'amount', 'meta'];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'integer',
        'likes' => 'integer',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dynamicColumns(): array
    {
        return ['id', 'user_id', 'title', 'content', 'status', 'count', 'likes', 'amount', 'created_at', 'updated_at', 'meta'];
    }

    public function dynamicRelations(): array
    {
        return [
            'user' => 'user_id'
        ];
    }

    public function dynamicFilters(): array
    {
        return ['id', 'user_id', 'title', 'content', 'status', 'count', 'created_at', 'likes', 'amount', 'user', 'user.name', 'meta'];
    }

    public function dynamicSorts(): array
    {
        return ['id', 'user_id', 'title', 'likes', 'created_at'];
    }

    public function dynamicGroups(): array
    {
        return ['id', 'user_id', 'status', 'created_at', 'amount'];
    }

    public function dynamicAppends(): array
    {
        return [
            'slug' => 'title'
        ];
    }

    public function getSlugAttribute()
    {
        return \Illuminate\Support\Str::slug($this->title);
    }
}
