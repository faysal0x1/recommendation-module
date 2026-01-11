<?php

use Illuminate\Support\Facades\Route;

Route::prefix('/api')->middleware([\App\Modules\Recommendation\Http\Middleware\EnsureRecommendationSession::class])->group(function () {
    Route::get('/recommendations', [\App\Modules\Recommendation\Http\Controllers\RecommendationController::class, 'index'])->name('modules.recommendations.index');
    Route::get('/recommendations/products', [\App\Modules\Recommendation\Http\Controllers\RecommendationController::class, 'getRecommendationsWithProducts'])->name('modules.recommendations.products');
    Route::get('/recently-viewed', [\App\Modules\Recommendation\Http\Controllers\RecommendationController::class, 'getRecentlyViewed'])->name('modules.recommendations.recently-viewed');
    Route::post('/track/engagement', [\App\Modules\Recommendation\Http\Controllers\RecommendationController::class, 'trackEngagement'])->name('modules.recommendations.track.engagement');
    Route::post('/track/interaction', [\App\Modules\Recommendation\Http\Controllers\RecommendationController::class, 'trackInteraction'])->name('modules.recommendations.track.interaction');
});
