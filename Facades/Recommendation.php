<?php
namespace App\Modules\Recommendation\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection recommend(array $params)
 * @see \App\Modules\Recommendation\Services\RecommendationService
 */
class Recommendation extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'modules.recommendation.service';
    }
}
