<?php
namespace App\Modules\Recommendation\Http\Middleware;

use App\Modules\Recommendation\Services\ProductViewTrackingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrackProductView
{
    protected ProductViewTrackingService $trackingService;

    public function __construct(ProductViewTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only track GET requests for product detail pages
        if (! $request->isMethod('GET')) {
            return $response;
        }

        // Check if this is a product detail page
        $product = $this->extractProductFromRequest($request);

        if ($product) {
            try {
                // Track the view asynchronously to avoid blocking the response
                $this->trackingService->trackView($product, $request);
            } catch (\Throwable $e) {
                // Log error but don't break the request
                Log::error('Failed to track product view', [
                    'product_id' => $product->id ?? null,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }

    /**
     * Extract product from request (check route parameters, request attributes, etc.)
     */
    protected function extractProductFromRequest(Request $request): ?\App\Models\Product
    {
        // Check if product is already loaded in request (from controller)
        if ($product = $request->attributes->get('product')) {
            return $product;
        }

        // Try to get product from route parameters
        $slug = $request->route('slug');
        if ($slug) {
            return \App\Models\Product::where('slug', $slug)->first();
        }

        // Check if product_id is in route parameters
        $productId = $request->route('product') ?? $request->route('productId') ?? $request->route('id');
        if ($productId) {
            return \App\Models\Product::find($productId);
        }

        // Check query parameters as fallback
        if ($productId = $request->query('product_id')) {
            return \App\Models\Product::find($productId);
        }

        return null;
    }
}
