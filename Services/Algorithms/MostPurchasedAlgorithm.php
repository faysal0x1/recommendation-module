<?php
namespace App\Modules\Recommendation\Services\Algorithms;

use App\Modules\Recommendation\Contracts\RecommendationAlgorithm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MostPurchasedAlgorithm implements RecommendationAlgorithm
{
    public function key(): string
    {
        return 'most_purchased_v1';
    }

    public function recommend(array $params, int $limit = 10): Collection
    {
        $categoryId = $params['category_id'] ?? null;
        $query      = DB::table('product_popularity')
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->orderByDesc('purchase_score')
            ->limit($limit * 2)
            ->get(['product_id', 'purchase_score']);

        return collect($query)->map(fn($row) => [
            'product_id' => (int) $row->product_id,
            'score'      => (float) $row->purchase_score,
            'reason'     => 'Best sellers in category',
            'algorithm'  => $this->key(),
            'metadata'   => [],
        ])->take($limit);
    }
}
