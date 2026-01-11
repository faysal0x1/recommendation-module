<?php
namespace App\Modules\Recommendation\Models;

use Illuminate\Database\Eloquent\Model;

class UserViewedProduct extends Model
{
    protected $table = 'user_viewed_products';

    protected $fillable = [
        'user_id',
        'product_id',
        'category_id',
        'brand_id',
        'first_viewed_at',
        'last_viewed_at',
        'view_count',
        'total_view_duration',
        'max_scroll_depth',
        'image_viewed',
        'specs_viewed',
        'reviews_viewed',
        'added_to_cart',
        'added_to_wishlist',
        'added_to_cart_at',
        'added_to_wishlist_at',
        'purchased',
        'purchased_at',
        'purchase_amount',
        'device_type',
        'browser',
        'os',
        'metadata',
    ];

    protected $casts = [
        'first_viewed_at'      => 'datetime',
        'last_viewed_at'       => 'datetime',
        'added_to_cart_at'     => 'datetime',
        'added_to_wishlist_at' => 'datetime',
        'purchased_at'         => 'datetime',
        'view_count'           => 'integer',
        'total_view_duration'  => 'integer',
        'max_scroll_depth'     => 'integer',
        'image_viewed'         => 'boolean',
        'specs_viewed'         => 'boolean',
        'reviews_viewed'       => 'boolean',
        'added_to_cart'        => 'boolean',
        'added_to_wishlist'    => 'boolean',
        'purchased'            => 'boolean',
        'purchase_amount'      => 'decimal:2',
        'metadata'             => 'array',
    ];

    /**
     * Relationship to User
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Relationship to Product
     */
    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    /**
     * Get recently viewed products for a user
     */
    public static function getRecentlyViewed(int $userId, int $limit = 10)
    {
        return static::where('user_id', $userId)
            ->with('product')
            ->orderByDesc('last_viewed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get most viewed products for a user
     */
    public static function getMostViewed(int $userId, int $limit = 10)
    {
        return static::where('user_id', $userId)
            ->with('product')
            ->orderByDesc('view_count')
            ->orderByDesc('last_viewed_at')
            ->limit($limit)
            ->get();
    }
}
