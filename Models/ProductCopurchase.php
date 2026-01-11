<?php
namespace App\Modules\Recommendation\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCopurchase extends Model
{
    protected $table = 'product_copurchase';

    protected $fillable = [
        'product_id',
        'copurchased_product_id',
        'count',
        'score',
    ];
}
