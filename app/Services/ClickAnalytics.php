<?php

namespace App\Services;

use App\Models\Link;
use App\Models\LinkClick;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregate queries over the link_clicks table.
 * Every query is done in SQL (GROUP BY / COUNT); PHP only reshapes the rows.
 */
class ClickAnalytics
{
    public function __construct(private readonly ?Link $link = null) {}

    /** Click query, optionally limited to one link and a start time. */
    private function clicks(?CarbonImmutable $since = null): Builder
    {
        return LinkClick::query()
            ->when($this->link, fn (Builder $q) => $q->where('link_id', $this->link->id))
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since));
    }

    public function totals(CarbonImmutable $since): array
    {
        return [
            'total_clicks' => $this->clicks($since)->count(),
            'unique_visitors' => $this->clicks($since)->distinct()->count('ip'),
        ];
    }

    /**
     * Clicks per day or per ISO week (weeks start Monday), zero-filled so
     * charts never have gaps. Keys are the first day of each bucket.
     *
     * @return array<int, array{date: string, clicks: int}>
     */
    public function timeSeries(CarbonImmutable $since, CarbonImmutable $until, string $interval = 'day'): array
    {
        $perDay = $this->clicks($since)
            ->selectRaw('date(created_at) as day, count(*) as clicks')
            ->groupBy('day')
            ->pluck('clicks', 'day');

        $bucket = fn (CarbonImmutable $d) => $interval === 'week'
            ? $d->startOfWeek()->toDateString()
            : $d->toDateString();

        // Build empty buckets first, then add the counted days into them.
        $series = [];
        for ($d = $since->startOfDay(); $d->lte($until); $d = $d->addDay()) {
            $series[$bucket($d)] ??= 0;
        }
        foreach ($perDay as $day => $count) {
            $key = $bucket(CarbonImmutable::parse($day));
            $series[$key] = ($series[$key] ?? 0) + (int) $count;
        }
        ksort($series);

        return collect($series)
            ->map(fn (int $clicks, string $date) => ['date' => $date, 'clicks' => $clicks])
            ->values()->all();
    }

    /**
     * Top referring hosts. Clicks with no Referer header are "Direct".
     *
     * @return array<int, array{referrer: string, clicks: int}>
     */
    public function topReferrers(CarbonImmutable $since, int $limit = 10): array
    {
        return $this->clicks($since)
            ->select('referrer', DB::raw('count(*) as clicks'))
            ->groupBy('referrer')
            ->get()
            ->groupBy(fn ($row) => $this->hostOf($row->referrer))
            ->map(fn (Collection $rows, string $host) => ['referrer' => $host, 'clicks' => (int) $rows->sum('clicks')])
            ->sortByDesc('clicks')->values()->take($limit)->all();
    }

    /**
     * Most-clicked links in the window (only meaningful without a single link).
     *
     * @return array<int, array{slug: string, destination_url: string, clicks: int}>
     */
    public function topLinks(CarbonImmutable $since, int $limit = 5): array
    {
        return LinkClick::query()
            ->join('links', 'links.id', '=', 'link_clicks.link_id')
            ->where('link_clicks.created_at', '>=', $since)
            ->groupBy('links.id', 'links.slug', 'links.destination_url')
            ->orderByDesc(DB::raw('count(*)'))
            ->limit($limit)
            ->get(['links.slug', 'links.destination_url', DB::raw('count(*) as clicks')])
            ->map(fn ($r) => ['slug' => $r->slug, 'destination_url' => $r->destination_url, 'clicks' => (int) $r->clicks])
            ->all();
    }

    private function hostOf(?string $referrer): string
    {
        $host = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;

        return $host ? strtolower(preg_replace('/^www\./i', '', $host)) : 'Direct';
    }
}
