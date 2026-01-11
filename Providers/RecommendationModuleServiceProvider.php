<?php
namespace App\Modules\Recommendation\Providers;

use App\Modules\Recommendation\Services\Algorithms\CrossSellAlgorithm;
use App\Modules\Recommendation\Services\Algorithms\FrequentlyBoughtTogetherAlgorithm;
use App\Modules\Recommendation\Services\Algorithms\MostPurchasedAlgorithm;
use App\Modules\Recommendation\Services\Algorithms\MostViewedAlgorithm;
use App\Modules\Recommendation\Services\Algorithms\PreviouslyViewedAlgorithm;
use App\Modules\Recommendation\Services\Algorithms\UpsellAlgorithm;
use App\Modules\Recommendation\Services\RecommendationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class RecommendationModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/recommendations.php', 'recommendations');

        $this->app->singleton(RecommendationService::class, function () {
            $service = new RecommendationService();
            $service->registerAlgorithm(new UpsellAlgorithm());
            $service->registerAlgorithm(new CrossSellAlgorithm());
            $service->registerAlgorithm(new MostViewedAlgorithm());
            $service->registerAlgorithm(new MostPurchasedAlgorithm());
            $service->registerAlgorithm(new PreviouslyViewedAlgorithm());
            $service->registerAlgorithm(new FrequentlyBoughtTogetherAlgorithm());
            return $service;
        });

        // Facade binding key
        $this->app->alias(RecommendationService::class, 'modules.recommendation.service');
    }

    public function boot(): void
    {
        $enabled = (bool) (Config::get('modules.recommendation.enabled', true));
        if (! $enabled) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register middleware alias
        $this->app['router']->aliasMiddleware(
            'track.product.view',
            \App\Modules\Recommendation\Http\Middleware\TrackProductView::class
        );
    }
}