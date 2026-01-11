<?php
namespace App\Modules\Recommendation\Models;

use Illuminate\Database\Eloquent\Model;

class UserBehaviorAnalytics extends Model
{
    protected $table = 'user_behavior_analytics';

    protected $fillable = [
        'user_id',
        'session_id',
        'product_id',
        'category_id',
        'session_started_at',
        'first_view_at',
        'last_view_at',
        'view_count',
        'total_view_duration',
        'max_scroll_depth',
        'image_views',
        'specs_views',
        'reviews_views',
        'added_to_cart',
        'added_to_wishlist',
        'shared',
        'return_visits',
        'purchased',
        'purchased_at',
        'purchase_amount',
        'device_type',
        'browser',
        'os',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'session_started_at'  => 'datetime',
        'first_view_at'       => 'datetime',
        'last_view_at'        => 'datetime',
        'purchased_at'        => 'datetime',
        'view_count'          => 'integer',
        'total_view_duration' => 'integer',
        'max_scroll_depth'    => 'integer',
        'image_views'         => 'integer',
        'specs_views'         => 'integer',
        'reviews_views'       => 'integer',
        'return_visits'       => 'integer',
        'added_to_cart'       => 'boolean',
        'added_to_wishlist'   => 'boolean',
        'shared'              => 'boolean',
        'purchased'           => 'boolean',
        'purchase_amount'     => 'decimal:2',
        'metadata'            => 'array',
    ];

    /**
     * Relationship to Product
     */
    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    /**
     * Relationship to User
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
