<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Article extends Model
{
    protected $fillable = [
        'title',
        'content',
        'link',
        'classification',
        'image_url',
        'source_feed',
        'source',
        'published_at',
        'summary',
        'expires_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the stopwords extracted from this article.
     */
    public function stopwords(): HasMany
    {
        return $this->hasMany(Stopword::class, 'source_article_id');
    }

    /**
     * Scope to get articles that haven't expired.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get articles by classification.
     */
    public function scopeByClassification($query, string $classification)
    {
        return $query->where('classification', $classification);
    }

    /**
     * Check if article has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
