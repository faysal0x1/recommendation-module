<?php
namespace App\Modules\Recommendation\Services\Algorithms;

use App\Modules\Recommendation\Contracts\RecommendationAlgorithm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpsellAlgorithm implements RecommendationAlgorithm
{
                                                 // Scoring weights for different criteria
    private const WEIGHT_PRICE_PROXIMITY = 0.35; // Closer price = higher score
    private const WEIGHT_CATEGORY_MATCH  = 0.25; // Same category hierarchy
    private const WEIGHT_BRAND_MATCH     = 0.20; // Same brand
    private const WEIGHT_PREMIUM_FLAGS   = 0.15; // Featured, deals, offers
    private const WEIGHT_PRICE_RANGE     = 0.05; // Reasonable price increase bonus

                                                    // Price increase range (percentage)
    private const MIN_PRICE_INCREASE_PERCENT = 10;  // At least 10% higher
    private const MAX_PRICE_INCREASE_PERCENT = 200; // At most 200% higher (3x)

    public function key(): string
    {
        return 'upsell_v1';
    }

    public function recommend(array $params, int $limit = 10): Collection
    {
        $productId = (int) ($params['product_id'] ?? 0);
        if (! $productId) {
            return collect();
        }

        // Get base product with all relevant fields
        $base = DB::table('products')
            ->where('id', $productId)
            ->first([
                'id',
                'category_id',
                'subcategory_id',
                'child_category_id',
                'brand_id',
                'final_price',
                'unit_price',
                'status',
            ]);

        if (! $base) {
            return collect();
        }

        // Use final_price if available, otherwise calculate from unit_price
        $basePrice = $this->getEffectivePrice($base);
        if ($basePrice <= 0) {
            return collect();
        }

        // Calculate price range
        $minPrice = $basePrice * (1 + self::MIN_PRICE_INCREASE_PERCENT / 100);
        $maxPrice = $basePrice * (1 + self::MAX_PRICE_INCREASE_PERCENT / 100);

        // Build query with all upselling criteria
        $query = DB::table('products')
            ->where('id', '!=', $base->id)
            ->where('status', 1) // Only active products
            ->where(function ($q) use ($basePrice, $minPrice, $maxPrice) {
                // Use final_price if available, otherwise calculate from unit_price
                $q->where(function ($subQ) use ($basePrice, $minPrice, $maxPrice) {
                    $subQ->whereNotNull('final_price')
                        ->where('final_price', '>', $basePrice)
                        ->whereBetween('final_price', [$minPrice, $maxPrice]);
                })->orWhere(function ($subQ) use ($basePrice, $minPrice, $maxPrice) {
                    // Fallback to unit_price if final_price is null
                    $subQ->whereNull('final_price')
                        ->where('unit_price', '>', $basePrice)
                        ->whereBetween('unit_price', [$minPrice, $maxPrice]);
                });
            })
            ->where(function ($q) {
                // Stock availability - primary check is qty > 0, also check stock field
                $q->where(function ($stockQ) {
                    $stockQ->whereRaw('CAST(qty AS UNSIGNED) > 0')
                        ->orWhere('stock', 'in_stock')
                        ->orWhere('stock', 'available')
                        ->orWhere('stock', 'In Stock')
                        ->orWhere('stock', 'Available');
                });
            })
            ->where(function ($q) {
                // Not call for price products (unless we want to include them)
                $q->where('call_for_price', false)
                    ->orWhereNull('call_for_price');
            });

        // Category-based filtering (prefer same category hierarchy)
        $this->applyCategoryFilter($query, $base);

        // Get candidates (fetch more than needed for scoring)
        $candidates = $query
            ->limit($limit * 5)
            ->get([
                'id',
                'category_id',
                'subcategory_id',
                'child_category_id',
                'brand_id',
                'final_price',
                'unit_price',
                'featured',
                'hot_deals',
                'special_offer',
                'special_deals',
                'status',
            ]);

        if ($candidates->isEmpty()) {
            return collect();
        }

        // Score each candidate using all criteria
        $scored = $candidates->map(function ($product) use ($base, $basePrice) {
            $scores = [
                'price_proximity' => $this->calculatePriceProximityScore($product, $basePrice),
                'category_match'  => $this->calculateCategoryMatchScore($product, $base),
                'brand_match'     => $this->calculateBrandMatchScore($product, $base),
                'premium_flags'   => $this->calculatePremiumFlagsScore($product),
                'price_range'     => $this->calculatePriceRangeScore($product, $basePrice),
            ];

            // Weighted total score
            $totalScore = (
                $scores['price_proximity'] * self::WEIGHT_PRICE_PROXIMITY +
                $scores['category_match'] * self::WEIGHT_CATEGORY_MATCH +
                $scores['brand_match'] * self::WEIGHT_BRAND_MATCH +
                $scores['premium_flags'] * self::WEIGHT_PREMIUM_FLAGS +
                $scores['price_range'] * self::WEIGHT_PRICE_RANGE
            );

            $productPrice         = $this->getEffectivePrice($product);
            $priceIncrease        = $productPrice - $basePrice;
            $priceIncreasePercent = ($priceIncrease / $basePrice) * 100;

            // Build reason text
            $reasons = [];
            if ($product->brand_id && $product->brand_id == $base->brand_id) {
                $reasons[] = 'same brand';
            }
            if ($product->category_id == $base->category_id) {
                $reasons[] = 'same category';
            } elseif ($product->subcategory_id && $product->subcategory_id == $base->subcategory_id) {
                $reasons[] = 'same subcategory';
            } elseif ($product->child_category_id && $product->child_category_id == $base->child_category_id) {
                $reasons[] = 'same child category';
            }
            if ($product->featured) {
                $reasons[] = 'featured';
            }
            if ($product->hot_deals) {
                $reasons[] = 'hot deal';
            }
            if ($product->special_offer) {
                $reasons[] = 'special offer';
            }
            $reasons[] = number_format($priceIncreasePercent, 1) . '% higher';

            return [
                'product_id' => (int) $product->id,
                'score'      => (float) $totalScore,
                'reason'     => 'Upsell — ' . implode(', ', $reasons),
                'algorithm'  => $this->key(),
                'metadata'   => [
                    'price_increase'         => round($priceIncrease, 2),
                    'price_increase_percent' => round($priceIncreasePercent, 2),
                    'base_price'             => round($basePrice, 2),
                    'product_price'          => round($productPrice, 2),
                    'criteria_scores'        => $scores,
                ],
            ];
        });

        // Sort by score descending and take top results
        return $scored
            ->sortByDesc('score')
            ->values()
            ->take($limit);
    }

    /**
     * Get effective price (final_price or calculated from unit_price)
     */
    private function getEffectivePrice($product): float
    {
        if (isset($product->final_price) && $product->final_price > 0) {
            return (float) $product->final_price;
        }

        if (isset($product->unit_price) && $product->unit_price > 0) {
            return (float) $product->unit_price;
        }

        return 0.0;
    }

    /**
     * Apply category-based filtering to query
     */
    private function applyCategoryFilter($query, $base): void
    {
        // Prefer products in same category hierarchy
        $query->where(function ($q) use ($base) {
            // Same category (always include)
            $q->where('category_id', $base->category_id);

            // Also include same subcategory if base has one
            if ($base->subcategory_id) {
                $q->orWhere(function ($subQ) use ($base) {
                    $subQ->where('subcategory_id', $base->subcategory_id);
                });
            }

            // Also include same child category if base has one
            if ($base->child_category_id) {
                $q->orWhere(function ($subQ) use ($base) {
                    $subQ->where('child_category_id', $base->child_category_id);
                });
            }
        });
    }

    /**
     * Calculate price proximity score (closer prices score higher)
     */
    private function calculatePriceProximityScore($product, float $basePrice): float
    {
        $productPrice = $this->getEffectivePrice($product);
        if ($productPrice <= $basePrice) {
            return 0.0;
        }

        $priceDiff   = $productPrice - $basePrice;
        $percentDiff = ($priceDiff / $basePrice) * 100;

        // Score decreases as price difference increases
        // Best score (1.0) for 10-30% increase, then decreases
        if ($percentDiff <= 30) {
            return 1.0;
        } elseif ($percentDiff <= 50) {
            return 0.8;
        } elseif ($percentDiff <= 100) {
            return 0.6;
        } else {
            return 0.4;
        }
    }

    /**
     * Calculate category match score
     */
    private function calculateCategoryMatchScore($product, $base): float
    {
        // Same child category (highest score)
        if ($base->child_category_id && $product->child_category_id == $base->child_category_id) {
            return 1.0;
        }

        // Same subcategory
        if ($base->subcategory_id && $product->subcategory_id == $base->subcategory_id) {
            return 0.8;
        }

        // Same category
        if ($product->category_id == $base->category_id) {
            return 0.6;
        }

        // Different category
        return 0.2;
    }

    /**
     * Calculate brand match score
     */
    private function calculateBrandMatchScore($product, $base): float
    {
        if (! $base->brand_id || ! $product->brand_id) {
            return 0.5; // Neutral score if no brand info
        }

        return $product->brand_id == $base->brand_id ? 1.0 : 0.3;
    }

    /**
     * Calculate premium flags score
     */
    private function calculatePremiumFlagsScore($product): float
    {
        $score = 0.5; // Base score

        if ($product->featured) {
            $score += 0.2;
        }
        if ($product->hot_deals) {
            $score += 0.15;
        }
        if ($product->special_offer) {
            $score += 0.1;
        }
        if ($product->special_deals) {
            $score += 0.05;
        }

        return min(1.0, $score);
    }

    /**
     * Calculate price range score (bonus for reasonable price increases)
     */
    private function calculatePriceRangeScore($product, float $basePrice): float
    {
        $productPrice    = $this->getEffectivePrice($product);
        $percentIncrease = (($productPrice - $basePrice) / $basePrice) * 100;

        // Sweet spot: 15-40% increase gets full score
        if ($percentIncrease >= 15 && $percentIncrease <= 40) {
            return 1.0;
        } elseif ($percentIncrease >= 10 && $percentIncrease <= 60) {
            return 0.8;
        } elseif ($percentIncrease >= 5 && $percentIncrease <= 100) {
            return 0.6;
        } else {
            return 0.4;
        }
    }
}