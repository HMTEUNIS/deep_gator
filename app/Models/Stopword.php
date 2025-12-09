<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stopword extends Model
{
    protected $fillable = [
        'word',
        'classification',
        'source_article_id',
    ];

    /**
     * Get the article that this stopword was extracted from.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'source_article_id');
    }

    /**
     * Scope to get stopwords by classification.
     */
    public function scopeByClassification($query, string $classification)
    {
        return $query->where('classification', $classification);
    }
}
