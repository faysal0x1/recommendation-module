<?php
namespace App\Modules\Recommendation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecomputeRecommendations extends Command
{
    protected $signature   = 'recs:recompute {--days=30}';
    protected $description = 'Recompute popularity and co-purchase tables from recent events';

    public function handle(): int
    {
        $days  = (int) $this->option('days');
        $since = now()->subDays($days);

        $this->info('Recomputing product_popularity...');
        $pop = DB::table('product_events')
            ->select('product_id')
            ->selectRaw("SUM(CASE WHEN event_type='product_view' THEN 1 ELSE 0 END) AS view_count")
            ->selectRaw("SUM(CASE WHEN event_type='purchase' THEN 1 ELSE 0 END) AS purchase_count")
            ->where('occurred_at', '>=', $since)
            ->groupBy('product_id')
            ->get();

        foreach ($pop as $row) {
            $categoryId = DB::table('products')->where('id', $row->product_id)->value('category_id') ?? 0;
            DB::table('product_popularity')->updateOrInsert(
                ['product_id' => $row->product_id],
                [
                    'category_id'    => (int) $categoryId,
                    'view_count'     => (int) $row->view_count,
                    'purchase_count' => (int) $row->purchase_count,
                    'view_score'     => (float) $row->view_count,
                    'purchase_score' => (float) ($row->purchase_count * 2 + $row->view_count * 0.5),
                    'computed_at'    => now(),
                ]
            );
        }

        $this->info('Recomputing product_copurchase...');
        $purchases = DB::table('product_events')
            ->where('event_type', 'purchase')
            ->where('occurred_at', '>=', $since)
            ->select('meta')
            ->get();

        $pairs = [];
        foreach ($purchases as $row) {
            $meta  = json_decode($row->meta ?? '[]', true) ?: [];
            $items = $meta['order_product_ids'] ?? [];
            sort($items);
            $count = count($items);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a              = (int) $items[$i];
                    $b              = (int) $items[$j];
                    $pairs["$a:$b"] = ($pairs["$a:$b"] ?? 0) + 1;
                }
            }
        }

        foreach ($pairs as $key => $cnt) {
            [$a, $b] = array_map('intval', explode(':', $key));
            DB::table('product_copurchase')->updateOrInsert(
                ['product_id' => $a, 'copurchased_product_id' => $b],
                ['count' => $cnt, 'score' => $cnt]
            );
            DB::table('product_copurchase')->updateOrInsert(
                ['product_id' => $b, 'copurchased_product_id' => $a],
                ['count' => $cnt, 'score' => $cnt]
            );
        }

        $this->info('Done');
        return self::SUCCESS;
    }
}
