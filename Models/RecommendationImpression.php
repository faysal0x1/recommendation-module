<?php
namespace App\Modules\Recommendation\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationImpression extends Model
{
    protected $table = 'recommendation_impressions';

    public $timestamps = false;

    protected $fillable = [
        'recommendation_id',
        'algorithm',
        'variant',
        'user_id',
        'session_id',
        'items',
        'shown_at',
    ];

    protected $casts = [
        'items'    => 'array',
        'shown_at' => 'datetime',
    ];
}
