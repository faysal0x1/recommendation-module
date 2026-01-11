<?php
namespace App\Modules\Recommendation\Services;

use App\Modules\Recommendation\Contracts\RecommendationAlgorithm;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class RecommendationService
{
    /** @var array<string, RecommendationAlgorithm> */
    protected array $algorithms = [];

    protected CacheRepository $cache;

    public function __construct()
    {
        $store = Config::get('recommendations.cache_store', 'redis');
        try {
            $this->cache = Cache::store($store);
        } catch (\Throwable $e) {
            $this->cache = Cache::store();
        }
    }

    public function registerAlgorithm(RecommendationAlgorithm $algorithm): void
    {
        $this->algorithms[$algorithm->key()] = $algorithm;
    }

    public function recommend(array $params): Collection
    {
        $limit        = (int) ($params['limit'] ?? 10);
        $algorithmKey = $this->resolveAlgorithmKey($params);

        $cacheKey = $this->buildCacheKey($algorithmKey, $params, $limit);
        $ttl      = (int) (Config::get('recommendations.cache_ttl.user_specific', 300));

        return $this->cache->remember($cacheKey, $ttl, function () use ($algorithmKey, $params, $limit) {
            $algorithm = $this->algorithms[$algorithmKey] ?? null;
            if (! $algorithm) {
                return collect();
            }

            $results = $algorithm->recommend($params, $limit);

            $filtered = $results->filter(function ($item) {
                $metadata = $item['metadata'] ?? [];
                if (! empty($metadata['out_of_stock'])) {
                    return false;
                }
                return true;
            })->values();

            return $filtered->take($limit);
        });
    }

    protected function resolveAlgorithmKey(array $params): string
    {
        $requested = (string) ($params['algorithm'] ?? '');
        if ($requested && isset($this->algorithms[$requested]) && $this->isEnabled($requested)) {
            return $requested;
        }

        $context          = (string) ($params['context'] ?? 'home');
        $defaultByContext = (array) Config::get('recommendations.defaults_by_context', []);
        $fallback         = (string) Config::get('recommendations.default_algorithm', 'most_viewed_v1');
        $key              = (string) ($defaultByContext[$context] ?? $fallback);
        if (! $this->isEnabled($key)) {
            return $fallback;
        }
        return $key;
    }

    protected function isEnabled(string $algorithmKey): bool
    {
        $enabled = (array) Config::get('recommendations.enabled', []);
        return in_array($algorithmKey, $enabled, true);
    }

    protected function buildCacheKey(string $algorithmKey, array $params, int $limit): string
    {
        $userId     = $params['user_id'] ?? null;
        $sessionId  = $params['session_id'] ?? null;
        $productId  = $params['product_id'] ?? null;
        $productIds = $params['product_ids'] ?? null;
        $context    = $params['context'] ?? 'home';

        // Handle product_ids array for cache key
        $productIdsKey = null;
        if (is_array($productIds) && ! empty($productIds)) {
            sort($productIds); // Sort for consistent cache key
            $productIdsKey = implode(',', $productIds);
        }

        $parts = [
            'recs', $algorithmKey, $context, (string) $userId, (string) $sessionId, (string) $productId, (string) $productIdsKey, (string) $limit,
        ];
        return implode(':', array_map(fn($p) => $p === null ? 'null' : $p, $parts));
    }
}