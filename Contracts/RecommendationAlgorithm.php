<?php
namespace App\Modules\Recommendation\Contracts;

use Illuminate\Support\Collection;

interface RecommendationAlgorithm
{
    public function recommend(array $params, int $limit = 10): Collection;
    public function key(): string;
}
