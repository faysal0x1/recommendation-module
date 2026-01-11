<?php
namespace App\Modules\Recommendation\Services;

use App\Models\Product;
use App\Modules\Recommendation\Models\ProductEvent;
use App\Modules\Recommendation\Models\UserBehaviorAnalytics;
use App\Modules\Recommendation\Models\UserViewedProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductViewTrackingService
{
    /**
     * Track a product view event
     */
    public function trackView(Product $product, Request $request): void
    {
        $userId    = auth()->id();
        $sessionId = $this->getSessionId($request);

        // Extract device and browser info
        $deviceInfo = $this->extractDeviceInfo($request);

        // Get product context
        $categoryId   = $product->category_id;
        $brandId      = $product->brand_id;
        $productPrice = $product->final_price ?? $product->unit_price ?? 0;

        // Extract UTM parameters
        $utmParams = $this->extractUtmParams($request);

        // Create product event
        ProductEvent::create([
            'event_type'    => 'product_view',
            'product_id'    => $product->getKey(),
            'user_id'       => $userId,
            'session_id'    => $sessionId,
            'category_id'   => $categoryId,
            'brand_id'      => $brandId,
            'product_price' => $productPrice,
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
            'referrer'      => $request->header('referer'),
            'utm_source'    => $utmParams['source'] ?? null,
            'utm_medium'    => $utmParams['medium'] ?? null,
            'utm_campaign'  => $utmParams['campaign'] ?? null,
            'device_type'   => $deviceInfo['device_type'],
            'browser'       => $deviceInfo['browser'],
            'os'            => $deviceInfo['os'],
            'occurred_at'   => now(),
            'meta'          => [
                'url'    => $request->fullUrl(),
                'route'  => $request->route()?->getName(),
                'method' => $request->method(),
            ],
        ]);

        // Update or create user behavior analytics
        $this->updateUserBehaviorAnalytics($product, $userId, $sessionId, $categoryId, $deviceInfo, $request);

        // If user is logged in, also store in user_viewed_products table
        if ($userId) {
            $this->updateUserViewedProducts($product, $userId, $categoryId, $brandId, $deviceInfo);
        }

        // Update product popularity (increment view count)
        $this->updateProductPopularity($product, $categoryId);
    }

    /**
     * Track additional interaction events
     */
    public function trackInteraction(string $eventType, Product $product, Request $request, array $metadata = []): void
    {
        $userId    = auth()->id();
        $sessionId = $this->getSessionId($request);

        $deviceInfo = $this->extractDeviceInfo($request);

        ProductEvent::create([
            'event_type'    => $eventType,
            'product_id'    => $product->getKey(),
            'user_id'       => $userId,
            'session_id'    => $sessionId,
            'category_id'   => $product->category_id,
            'brand_id'      => $product->brand_id,
            'product_price' => $product->final_price ?? $product->unit_price ?? 0,
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
            'device_type'   => $deviceInfo['device_type'],
            'browser'       => $deviceInfo['browser'],
            'os'            => $deviceInfo['os'],
            'occurred_at'   => now(),
            'meta'          => array_merge([
                'url' => $request->fullUrl(),
            ], $metadata),
        ]);

        // Update behavior analytics based on event type
        $this->updateBehaviorForInteraction($eventType, $product, $userId, $sessionId);

        // If user is logged in, also update user_viewed_products
        if ($userId) {
            $this->updateUserViewedProductsInteraction($eventType, $product, $userId);
        }
    }

    /**
     * Track view duration and scroll depth
     */
    public function trackEngagement(int $productId, int $userId, ?string $sessionId, array $engagementData): void
    {
        $updateData = [];

        if (isset($engagementData['view_duration'])) {
            $updateData['view_duration'] = $engagementData['view_duration'];
        }

        if (isset($engagementData['scroll_depth'])) {
            $updateData['scroll_depth'] = $engagementData['scroll_depth'];
        }

        if (isset($engagementData['image_viewed'])) {
            $updateData['image_viewed'] = $engagementData['image_viewed'];
        }

        if (isset($engagementData['specs_viewed'])) {
            $updateData['specs_viewed'] = $engagementData['specs_viewed'];
        }

        if (isset($engagementData['reviews_viewed'])) {
            $updateData['reviews_viewed'] = $engagementData['reviews_viewed'];
        }

        if (! empty($updateData)) {
            // Update the most recent product event
            $productIdValue = is_int($productId) ? $productId : (int) $productId;
            ProductEvent::where('product_id', $productIdValue)
                ->where('event_type', 'product_view')
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->when(! $userId && $sessionId, fn($q) => $q->where('session_id', $sessionId))
                ->orderByDesc('occurred_at')
                ->limit(1)
                ->update($updateData);
        }

        // Update behavior analytics
        if ($userId || $sessionId) {
            $behavior = UserBehaviorAnalytics::where('product_id', (int) $productId)
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->when(! $userId && $sessionId, fn($q) => $q->where('session_id', $sessionId))
                ->first();

            if ($behavior) {
                if (isset($engagementData['view_duration'])) {
                    $behavior->increment('total_view_duration', $engagementData['view_duration']);
                }
                if (isset($engagementData['scroll_depth']) && $engagementData['scroll_depth'] > $behavior->max_scroll_depth) {
                    $behavior->max_scroll_depth = $engagementData['scroll_depth'];
                }
                if (isset($engagementData['image_viewed']) && $engagementData['image_viewed']) {
                    $behavior->increment('image_views');
                }
                if (isset($engagementData['specs_viewed']) && $engagementData['specs_viewed']) {
                    $behavior->increment('specs_views');
                }
                if (isset($engagementData['reviews_viewed']) && $engagementData['reviews_viewed']) {
                    $behavior->increment('reviews_views');
                }
                $behavior->save();
            }
        }

        // If user is logged in, also update user_viewed_products
        if ($userId) {
            $this->updateUserViewedProductsEngagement($userId, (int) $productId, $engagementData);
        }
    }

    /**
     * Get or create session ID
     */
    protected function getSessionId(Request $request): string
    {
        // Check cookie first
        $sessionId = $request->cookie('rec_session');
        if ($sessionId) {
            return $sessionId;
        }

        // Check session
        if (! $request->hasSession() || ! $sessionId = $request->session()->get('rec_session_id')) {
            $sessionId = (string) Str::uuid();
            if ($request->hasSession()) {
                $request->session()->put('rec_session_id', $sessionId);
            }
        }

        return $sessionId;
    }

    /**
     * Extract device information from request
     */
    protected function extractDeviceInfo(Request $request): array
    {
        $userAgent = $request->userAgent() ?? '';

        // Detect device type
        $deviceType = 'desktop';
        if (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $userAgent)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/tablet|ipad|playbook|silk/i', $userAgent)) {
            $deviceType = 'tablet';
        }

        // Detect browser
        $browser = 'unknown';
        if (preg_match('/chrome/i', $userAgent) && ! preg_match('/edg/i', $userAgent)) {
            $browser = 'chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $browser = 'firefox';
        } elseif (preg_match('/safari/i', $userAgent) && ! preg_match('/chrome/i', $userAgent)) {
            $browser = 'safari';
        } elseif (preg_match('/edg/i', $userAgent)) {
            $browser = 'edge';
        } elseif (preg_match('/opera|opr/i', $userAgent)) {
            $browser = 'opera';
        }

        // Detect OS
        $os = 'unknown';
        if (preg_match('/windows/i', $userAgent)) {
            $os = 'windows';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $os = 'macos';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $os = 'linux';
        } elseif (preg_match('/android/i', $userAgent)) {
            $os = 'android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $os = 'ios';
        }

        return [
            'device_type' => $deviceType,
            'browser'     => $browser,
            'os'          => $os,
        ];
    }

    /**
     * Extract UTM parameters from request
     */
    protected function extractUtmParams(Request $request): array
    {
        return [
            'source'   => $request->query('utm_source'),
            'medium'   => $request->query('utm_medium'),
            'campaign' => $request->query('utm_campaign'),
        ];
    }

    /**
     * Update or create user behavior analytics
     */
    protected function updateUserBehaviorAnalytics(
        Product $product,
        ?int $userId,
        string $sessionId,
        ?int $categoryId,
        array $deviceInfo,
        Request $request
    ): void {
        $behavior = UserBehaviorAnalytics::firstOrNew([
            'user_id'    => $userId,
            'session_id' => $sessionId,
            'product_id' => $product->getKey(),
        ]);

        if (! $behavior->exists) {
            $behavior->category_id        = $categoryId;
            $behavior->session_started_at = now();
            $behavior->first_view_at      = now();
            $behavior->device_type        = $deviceInfo['device_type'];
            $behavior->browser            = $deviceInfo['browser'];
            $behavior->os                 = $deviceInfo['os'];
            $behavior->ip_address         = $request->ip();
        } else {
            // Increment view count and update last view
            $behavior->increment('view_count');
            $behavior->increment('return_visits');
        }

        $behavior->last_view_at = now();
        $behavior->save();
    }

    /**
     * Update product popularity
     */
    protected function updateProductPopularity(Product $product, ?int $categoryId): void
    {
        DB::table('product_popularity')->updateOrInsert(
            ['product_id' => $product->getKey()],
            [
                'category_id' => $categoryId ?? 0,
                'view_count'  => DB::raw('view_count + 1'),
                'view_score'  => DB::raw('view_score + 1'),
                'computed_at' => now(),
            ]
        );
    }

    /**
     * Update behavior analytics for specific interactions
     */
    protected function updateBehaviorForInteraction(
        string $eventType,
        Product $product,
        ?int $userId,
        ?string $sessionId
    ): void {
        $behavior = UserBehaviorAnalytics::where('product_id', $product->getKey())
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when(! $userId && $sessionId, fn($q) => $q->where('session_id', $sessionId))
            ->first();

        if ($behavior) {
            switch ($eventType) {
                case 'add_to_cart':
                    $behavior->added_to_cart = true;
                    break;
                case 'add_to_wishlist':
                    $behavior->added_to_wishlist = true;
                    break;
                case 'share':
                    $behavior->shared = true;
                    break;
                case 'purchase':
                    $behavior->purchased    = true;
                    $behavior->purchased_at = now();
                    break;
            }
            $behavior->save();
        }
    }

    /**
     * Update or create user viewed products record (for logged-in users only)
     */
    protected function updateUserViewedProducts(
        Product $product,
        int $userId,
        ?int $categoryId,
        ?int $brandId,
        array $deviceInfo
    ): void {
        $viewedProduct = UserViewedProduct::firstOrNew([
            'user_id'    => $userId,
            'product_id' => $product->getKey(),
        ]);

        // If it's a new record, set initial values
        if (! $viewedProduct->exists) {
            $viewedProduct->category_id     = $categoryId;
            $viewedProduct->brand_id        = $brandId;
            $viewedProduct->first_viewed_at = now();
            $viewedProduct->device_type     = $deviceInfo['device_type'];
            $viewedProduct->browser         = $deviceInfo['browser'];
            $viewedProduct->os              = $deviceInfo['os'];
        } else {
            // Increment view count for existing records
            $viewedProduct->increment('view_count');
        }

        // Always update last viewed timestamp
        $viewedProduct->last_viewed_at = now();
        $viewedProduct->save();
    }

    /**
     * Update user viewed products when engagement data is tracked
     */
    public function updateUserViewedProductsEngagement(int $userId, int $productId, array $engagementData): void
    {
        $viewedProduct = UserViewedProduct::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($viewedProduct) {
            if (isset($engagementData['view_duration'])) {
                $viewedProduct->increment('total_view_duration', $engagementData['view_duration']);
            }
            if (isset($engagementData['scroll_depth']) && $engagementData['scroll_depth'] > $viewedProduct->max_scroll_depth) {
                $viewedProduct->max_scroll_depth = $engagementData['scroll_depth'];
            }
            if (isset($engagementData['image_viewed']) && $engagementData['image_viewed']) {
                $viewedProduct->image_viewed = true;
            }
            if (isset($engagementData['specs_viewed']) && $engagementData['specs_viewed']) {
                $viewedProduct->specs_viewed = true;
            }
            if (isset($engagementData['reviews_viewed']) && $engagementData['reviews_viewed']) {
                $viewedProduct->reviews_viewed = true;
            }
            $viewedProduct->save();
        }
    }

    /**
     * Update user viewed products for interactions
     */
    protected function updateUserViewedProductsInteraction(string $eventType, Product $product, int $userId): void
    {
        $viewedProduct = UserViewedProduct::where('user_id', $userId)
            ->where('product_id', $product->getKey())
            ->first();

        if ($viewedProduct) {
            switch ($eventType) {
                case 'add_to_cart':
                    $viewedProduct->added_to_cart    = true;
                    $viewedProduct->added_to_cart_at = now();
                    break;
                case 'add_to_wishlist':
                    $viewedProduct->added_to_wishlist    = true;
                    $viewedProduct->added_to_wishlist_at = now();
                    break;
                case 'purchase':
                    $viewedProduct->purchased       = true;
                    $viewedProduct->purchased_at    = now();
                    $viewedProduct->purchase_amount = $product->final_price ?? $product->unit_price ?? 0;
                    break;
            }
            $viewedProduct->save();
        }
    }
}
