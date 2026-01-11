<?php
namespace App\Modules\Recommendation\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPopularity extends Model
{
    protected $table = 'product_popularity';

    public $timestamps = false;

    protected $primaryKey = 'product_id';
    public $incrementing  = false;

    protected $fillable = [
        'product_id',
        'category_id',
        'view_count',
        'purchase_count',
        'view_score',
        'purchase_score',
        'computed_at',
    ];

    protected $casts = [
        'computed_at' => 'datetime',
    ];
}
