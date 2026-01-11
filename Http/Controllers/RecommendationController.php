<?php
namespace App\Modules\Recommendation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Recommendation\Http\Requests\RecommendationRequest;
use App\Modules\Recommendation\Jobs\LogRecommendationImpression;
use App\Modules\Recommendation\Services\ProductViewTrackingService;
use App\Modules\Recommendation\Services\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class RecommendationController extends Controller
{
    public function __construct(
        private RecommendationService $service,
        private ProductViewTrackingService $trackingService
    ) {}

    public function index(RecommendationRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['user_id'])) {
            $validated['session_id'] = $validated['session_id'] ?? ($_COOKIE['rec_session'] ?? null);
        }

        $validated['recommendation_id'] = (string) Str::uuid();
        $results                        = $this->service->recommend($validated)->values();

        $algorithmUsed = $results->first()['algorithm'] ?? ($validated['algorithm'] ?? '');
        $variant       = $validated['variant'] ?? null;

        $useQueue = (bool) Config::get('recommendations.telemetry.use_queue', true);
        if ($useQueue) {
            LogRecommendationImpression::dispatch(
                $validated['recommendation_id'],
                (string) $algorithmUsed,
                $variant ? (string) $variant : null,
                $validated['user_id'] ?? null,
                $validated['session_id'] ?? null,
                $results->all()
            )->onQueue('default');
        } else {
            (new LogRecommendationImpression(
                $validated['recommendation_id'],
                (string) $algorithmUsed,
                $variant ? (string) $variant : null,
                $validated['user_id'] ?? null,
                $validated['session_id'] ?? null,
                $results->all()
            ))->handle();
        }

        return response()
            ->json($results)
            ->withHeaders([
                'X-Recommendation-Id'        => $validated['recommendation_id'],
                'X-Recommendation-Algorithm' => (string) $algorithmUsed,
                'X-Recommendation-Variant'   => (string) ($variant ?? ''),
            ]);
    }

    /**
     * Track user engagement (view duration, scroll depth, etc.)
     */
    public function trackEngagement(Request $request)
    {
        $request->validate([
            'product_id'     => 'required|integer|exists:products,id',
            'view_duration'  => 'nullable|integer|min:0',
            'scroll_depth'   => 'nullable|integer|min:0|max:100',
            'image_viewed'   => 'nullable|boolean',
            'specs_viewed'   => 'nullable|boolean',
            'reviews_viewed' => 'nullable|boolean',
        ]);

        $product   = Product::findOrFail($request->product_id);
        $userId    = Auth::check() ? Auth::id() : null;
        $sessionId = $request->cookie('rec_session') ?? $request->session()->get('rec_session_id');

        $this->trackingService->trackEngagement(
            $product->id,
            $userId,
            $sessionId,
            $request->only(['view_duration', 'scroll_depth', 'image_viewed', 'specs_viewed', 'reviews_viewed'])
        );

        return response()->json(['success' => true]);
    }

    /**
     * Track user interactions (add to cart, wishlist, share, etc.)
     */
    public function trackInteraction(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'event_type' => 'required|string|in:add_to_cart,add_to_wishlist,remove_from_cart,remove_from_wishlist,share,click,view_image,view_specs,view_reviews',
            'metadata'   => 'nullable|array',
        ]);

        $product = Product::findOrFail($request->product_id);

        $this->trackingService->trackInteraction(
            $request->event_type,
            $product,
            $request,
            $request->get('metadata', [])
        );

        return response()->json(['success' => true]);
    }

    /**
     * Get recently viewed products for search suggestions
     */
    public function getRecentlyViewed(Request $request)
    {
        $userId    = Auth::check() ? Auth::id() : null;
        $sessionId = $request->cookie('rec_session') ?? $request->session()->get('rec_session_id');
        $limit     = (int) ($request->input('limit', 8));

        // Use previously viewed algorithm to get recommendations
        $recommendations = $this->service->recommend([
            'context'    => 'product_page',
            'user_id'    => $userId,
            'session_id' => $sessionId,
            'algorithm'  => 'previously_viewed_v1',
            'limit'      => $limit,
        ]);

        // Extract product IDs
        $productIds = $recommendations->pluck('product_id')->toArray();

        if (empty($productIds)) {
            return response()->json(['data' => []]);
        }

        // Fetch product details
        $products = Product::with(['brand:id,name,slug,image_url'])
            ->whereIn('id', $productIds)
            ->where('status', 1)
            ->get()
            ->map(function ($product) {
                $imagePath = $product->image_url ?: null;
                $updatedAt = $product->updated_at ? (is_string($product->updated_at) ? $product->updated_at : $product->updated_at->toDateTimeString()) : null;
                $imageUrl  = $imagePath
                    ? (\App\Http\Controllers\FrontendController::getCacheBustedImageUrl($imagePath, $updatedAt))
                    : '/placeholder.svg';

                return [
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'slug'           => $product->slug,
                    'image_url'      => $imageUrl,
                    'unit_price'     => (float) ($product->unit_price ?? 0),
                    'final_price'    => (float) ($product->final_price ?? 0),
                    'call_for_price' => $product->call_for_price ?? false,
                    'brand'          => $product->brand ? [
                        'id'   => $product->brand->id,
                        'name' => $product->brand->name,
                    ] : null,
                ];
            })
            ->values();

        // Maintain order from recommendations
        $orderedProducts = collect($productIds)
            ->map(function ($id) use ($products) {
                return $products->firstWhere('id', $id);
            })
            ->filter()
            ->values();

        return response()->json(['data' => $orderedProducts]);
    }

    /**
     * Get recommendations with full product details
     */
    public function getRecommendationsWithProducts(RecommendationRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['user_id'])) {
            $validated['session_id'] = $validated['session_id'] ?? ($_COOKIE['rec_session'] ?? null);
        }

        // Handle product_ids array from query parameters
        if ($request->has('product_ids')) {
            $inputProductIds = $request->input('product_ids');
            if (is_array($inputProductIds)) {
                $validated['product_ids'] = array_filter(array_map('intval', $inputProductIds));
            }
        }

        $validated['recommendation_id'] = (string) Str::uuid();
        $recommendations                = $this->service->recommend($validated)->values();

        // Extract product IDs from recommendations
        $recommendedProductIds = $recommendations->pluck('product_id')->toArray();

        if (empty($recommendedProductIds)) {
            return response()->json(['data' => []]);
        }

        // Fetch product details with relationships
        $products = Product::with([
            'brand:id,name,slug,image_url',
            'product_reviews:id,product_id',
            'media',
        ])
            ->whereIn('id', $recommendedProductIds)
            ->where('status', 1)
            ->get()
            ->map(function ($product) {
                $imagePath = $product->image_url ?: null;
                $updatedAt = $product->updated_at ? (is_string($product->updated_at) ? $product->updated_at : $product->updated_at->toDateTimeString()) : null;
                $imageUrl  = $imagePath
                    ? (\App\Http\Controllers\FrontendController::getCacheBustedImageUrl($imagePath, $updatedAt))
                    : '/placeholder.svg';

                // Get multi images from media collection (filter by 'multi_images' collection)
                $multiImages = $product->getMedia('multi_images')->map(function ($media) {
                    return [
                        'url'   => $media->getUrl(),
                        'photo' => $media->getUrl(),
                    ];
                })->toArray();

                return [
                    'id'                    => $product->id,
                    'name'                  => $product->name,
                    'slug'                  => $product->slug,
                    'image_url'             => $imageUrl,
                    'product_thumbnail'     => $imageUrl,
                    'unit_price'            => (float) ($product->unit_price ?? 0),
                    'final_price'           => (float) ($product->final_price ?? 0),
                    'call_for_price'        => $product->call_for_price ?? false,
                    'call_for_price_number' => $product->call_for_price_number ?? '8801713991638',
                    'brand'                 => $product->brand ? [
                        'id'        => $product->brand->id,
                        'name'      => $product->brand->name,
                        'slug'      => $product->brand->slug,
                        'image_url' => $product->brand->image_url,
                    ] : null,
                    'product_reviews'       => $product->product_reviews ?? [],
                    'multi_images'          => $multiImages,
                    'hot_deals'             => $product->hot_deals ?? false,
                    'featured'              => $product->featured ?? false,
                ];
            })
            ->values();

        // Maintain order from recommendations
        $orderedProducts = collect($recommendedProductIds)
            ->map(function ($id) use ($products) {
                return $products->firstWhere('id', $id);
            })
            ->filter()
            ->values();

        $algorithmUsed = $recommendations->first()['algorithm'] ?? ($validated['algorithm'] ?? '');
        $variant       = $validated['variant'] ?? null;

        // Log recommendation impression
        $useQueue = (bool) Config::get('recommendations.telemetry.use_queue', true);
        if ($useQueue) {
            LogRecommendationImpression::dispatch(
                $validated['recommendation_id'],
                (string) $algorithmUsed,
                $variant ? (string) $variant : null,
                $validated['user_id'] ?? null,
                $validated['session_id'] ?? null,
                $recommendations->all()
            )->onQueue('default');
        } else {
            (new LogRecommendationImpression(
                $validated['recommendation_id'],
                (string) $algorithmUsed,
                $variant ? (string) $variant : null,
                $validated['user_id'] ?? null,
                $validated['session_id'] ?? null,
                $recommendations->all()
            ))->handle();
        }

        return response()
            ->json(['data' => $orderedProducts])
            ->withHeaders([
                'X-Recommendation-Id'        => $validated['recommendation_id'],
                'X-Recommendation-Algorithm' => (string) $algorithmUsed,
                'X-Recommendation-Variant'   => (string) ($variant ?? ''),
            ]);
    }
}
