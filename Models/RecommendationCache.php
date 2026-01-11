<?php
namespace App\Modules\Recommendation\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationCache extends Model
{
    protected $table = 'recommendation_cache';

    public $timestamps = false;

    protected $fillable = [
        'algorithm',
        'context',
        'user_id',
        'session_id',
        'product_id',
        'results',
        'cached_at',
    ];

    protected $casts = [
        'results'   => 'array',
        'cached_at' => 'datetime',
    ];
}
