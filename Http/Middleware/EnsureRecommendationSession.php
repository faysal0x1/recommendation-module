<?php
namespace App\Modules\Recommendation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EnsureRecommendationSession
{
    public function handle(Request $request, Closure $next)
    {
        $cookieName = 'rec_session';
        $sessionId  = $request->cookies->get($cookieName);
        if (! $sessionId) {
            $sessionId = (string) Str::uuid();
        }

        $response = $next($request);
        $minutes  = 60 * 24 * 180;
        return $response->cookie($cookieName, $sessionId, $minutes, null, null, false, false, false, 'Lax');
    }
}
