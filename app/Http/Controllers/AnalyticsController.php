<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Services\ClickAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnalyticsController extends Controller
{
    /** GET /api/links/{slug}/analytics?days=30&interval=day|week */
    public function link(Request $request, string $slug)
    {
        $link = Link::where('slug', Str::lower($slug))->firstOrFail();

        return $this->report($request, new ClickAnalytics($link), [
            'link' => $link->only(['slug', 'destination_url']),
        ]);
    }

    /** GET /api/analytics?days=30&interval=day|week  (all links) */
    public function overview(Request $request)
    {
        $analytics = new ClickAnalytics;
        $since = $this->window($request)[0];

        return $this->report($request, $analytics, [
            'top_links' => $analytics->topLinks($since),
        ]);
    }

    private function report(Request $request, ClickAnalytics $analytics, array $extra)
    {
        $request->validate([
            'days' => ['nullable', 'integer', 'between:1,365'],
            'interval' => ['nullable', 'in:day,week'],
        ]);

        [$since, $until, $days] = $this->window($request);
        $interval = $request->input('interval', 'day');

        return response()->json([
            ...$extra,
            'range' => ['days' => $days, 'from' => $since->toDateString(), 'to' => $until->toDateString(), 'interval' => $interval],
            ...$analytics->totals($since),
            'series' => $analytics->timeSeries($since, $until, $interval),
            'top_referrers' => $analytics->topReferrers($since),
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int} */
    private function window(Request $request): array
    {
        $days = min(365, max(1, (int) $request->input('days', 30)));
        $until = CarbonImmutable::now();

        return [$until->subDays($days - 1)->startOfDay(), $until, $days];
    }
}
