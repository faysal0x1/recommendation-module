<?php
namespace App\Modules\Recommendation\Models;

use Illuminate\Database\Eloquent\Model;

class ProductEvent extends Model
{
    protected $table = 'product_events';

    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'product_id',
        'user_id',
        'session_id',
        'cart_id',
        'category_id',
        'brand_id',
        'product_price',
        'ip_address',
        'user_agent',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'view_duration',
        'scroll_depth',
        'image_viewed',
        'specs_viewed',
        'reviews_viewed',
        'device_type',
        'browser',
        'os',
        'occurred_at',
        'meta',
    ];

    protected $casts = [
        'occurred_at'    => 'datetime',
        'meta'           => 'array',
        'product_price'  => 'decimal:2',
        'view_duration'  => 'integer',
        'scroll_depth'   => 'integer',
        'image_viewed'   => 'boolean',
        'specs_viewed'   => 'boolean',
        'reviews_viewed' => 'boolean',
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
