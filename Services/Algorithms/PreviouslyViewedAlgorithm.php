<?php
namespace App\Modules\Recommendation\Services\Algorithms;

use App\Modules\Recommendation\Contracts\RecommendationAlgorithm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PreviouslyViewedAlgorithm implements RecommendationAlgorithm
{
    public function key(): string
    {
        return 'previously_viewed_v1';
    }

    public function recommend(array $params, int $limit = 10): Collection
    {
        $userId    = $params['user_id'] ?? null;
        $sessionId = $params['session_id'] ?? null;
        if (! $userId && ! $sessionId) {
            return collect();
        }

        // If user is logged in, use user_viewed_products table for better performance
        if ($userId) {
            $rows = DB::table('user_viewed_products')
                ->where('user_id', $userId)
                ->orderByDesc('last_viewed_at')
                ->limit($limit * 2)
                ->get(['product_id', 'last_viewed_at', 'view_count']);

            return collect($rows)->map(function ($row) {
                // Score based on recency and view count
                $recencyScore   = strtotime($row->last_viewed_at) / 1000000000;
                $viewCountScore = $row->view_count * 0.1;
                $score          = $recencyScore + $viewCountScore;

                return [
                    'product_id' => (int) $row->product_id,
                    'score'      => (float) $score,
                    'reason'     => 'Previously viewed' . ($row->view_count > 1 ? " ({$row->view_count} times)" : ''),
                    'algorithm' => $this->key(),
                    'metadata'  => [
                        'view_count'     => (int) $row->view_count,
                        'last_viewed_at' => $row->last_viewed_at,
                    ],
                ];
            })->sortByDesc('score')->values()->take($limit);
        }

        // Fallback to product_events for session-based tracking
        $rows = DB::table('product_events')
            ->where('session_id', $sessionId)
            ->where('event_type', 'product_view')
            ->orderByDesc('occurred_at')
            ->limit($limit * 3)
            ->get(['product_id', 'occurred_at']);

        $seen = [];
        $recs = [];
        foreach ($rows as $row) {
            if (isset($seen[$row->product_id])) {
                continue;
            }
            $seen[$row->product_id] = true;
            $recs[]                 = [
                'product_id' => (int) $row->product_id,
                'score'      => (float) (strtotime($row->occurred_at) / 1000000000),
                'reason'     => 'Previously viewed',
                'algorithm'  => $this->key(),
                'metadata'   => [],
            ];
            if (count($recs) >= $limit) {
                break;
            }
        }

        return collect($recs);
    }
}
