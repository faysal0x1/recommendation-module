<?php
namespace App\Modules\Recommendation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class LogRecommendationImpression implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $recommendationId,
        public string $algorithm,
        public ?string $variant,
        public ?int $userId,
        public ?string $sessionId,
        public array $items
    ) {}

    public function handle(): void
    {
        DB::table('recommendation_impressions')->insert([
            'recommendation_id' => $this->recommendationId,
            'algorithm'         => $this->algorithm,
            'variant'           => $this->variant,
            'user_id'           => $this->userId,
            'session_id'        => $this->sessionId,
            'items'             => json_encode($this->items),
            'shown_at'          => now(),
        ]);
    }
}
