<?php
namespace App\Modules\Recommendation\Services\Algorithms;

use App\Modules\Recommendation\Contracts\RecommendationAlgorithm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CrossSellAlgorithm implements RecommendationAlgorithm
{
    public function key(): string
    {
        return 'cross_sell_v1';
    }

    public function recommend(array $params, int $limit = 10): Collection
    {
        $productId = (int) ($params['product_id'] ?? 0);
        if (! $productId) {
            return collect();
        }

        $rows = DB::table('product_copurchase')
            ->where('product_id', $productId)
            ->orderByDesc('score')
            ->limit($limit * 2)
            ->get(['copurchased_product_id as product_id', 'score']);

        return collect($rows)->map(fn($row) => [
            'product_id' => (int) $row->product_id,
            'score'      => (float) $row->score,
            'reason'     => 'Cross-sell — commonly bought with this',
            'algorithm'  => $this->key(),
            'metadata'   => [],
        ])->take($limit);
    }
}
