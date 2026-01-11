<?php
namespace App\Modules\Recommendation\Services\Algorithms;

use App\Modules\Recommendation\Contracts\RecommendationAlgorithm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MostViewedAlgorithm implements RecommendationAlgorithm
{
    public function key(): string
    {
        return 'most_viewed_v1';
    }

    public function recommend(array $params, int $limit = 10): Collection
    {
        $categoryId = $params['category_id'] ?? null;
        $query      = DB::table('product_popularity')
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->orderByDesc('view_score')
            ->limit($limit * 2)
            ->get(['product_id', 'view_score']);

        return collect($query)->map(fn($row) => [
            'product_id' => (int) $row->product_id,
            'score'      => (float) $row->view_score,
            'reason'     => 'Most viewed in category',
            'algorithm'  => $this->key(),
            'metadata'   => [],
        ])->take($limit);
    }
}
