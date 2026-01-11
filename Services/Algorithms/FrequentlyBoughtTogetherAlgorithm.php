<?php
namespace App\Modules\Recommendation\Services\Algorithms;

use App\Modules\Recommendation\Contracts\RecommendationAlgorithm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FrequentlyBoughtTogetherAlgorithm implements RecommendationAlgorithm
{
    public function key(): string
    {
        return 'fbt_v1';
    }

    public function recommend(array $params, int $limit = 10): Collection
    {
        $seedIds = [];
        if (! empty($params['product_ids']) && is_array($params['product_ids'])) {
            $seedIds = array_values(array_filter(array_map('intval', $params['product_ids'])));
        } elseif (! empty($params['product_id'])) {
            $seedIds = [(int) $params['product_id']];
        }
        if (empty($seedIds)) {
            return collect();
        }

        $rows = DB::table('product_copurchase')
            ->whereIn('product_id', $seedIds)
            ->select('copurchased_product_id as product_id')
            ->selectRaw('SUM(score) as total_score')
            ->groupBy('copurchased_product_id')
            ->orderByDesc('total_score')
            ->limit($limit * 3)
            ->get();

        $exclude = array_flip($seedIds);
        $recs    = [];
        foreach ($rows as $row) {
            $pid = (int) $row->product_id;
            if (isset($exclude[$pid])) {
                continue;
            }
            $recs[] = [
                'product_id' => $pid,
                'score'      => (float) $row->total_score,
                'reason'     => 'Frequently bought together',
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
