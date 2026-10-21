<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\LinkClick;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function click(Link $link, string $when, ?string $ref = null, string $ip = '1.1.1.1'): void
    {
        LinkClick::factory()->create([
            'link_id' => $link->id, 'created_at' => $when, 'referrer' => $ref, 'ip' => $ip,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00:00'));
    }

    public function test_link_analytics_totals_series_and_referrers(): void
    {
        $link = Link::factory()->create(['slug' => 'demo']);
        $this->click($link, '2026-10-21 09:00:00', 'https://www.google.com/search?q=x', '1.1.1.1');
        $this->click($link, '2026-10-21 10:00:00', 'https://google.com/', '2.2.2.2');
        $this->click($link, '2026-10-19 10:00:00', null, '1.1.1.1');
        $this->click($link, '2026-09-01 10:00:00', 'https://old.example', '3.3.3.3'); // outside window

        $res = $this->getJson('/api/links/demo/analytics?days=7')->assertOk();

        $res->assertJsonPath('total_clicks', 3)->assertJsonPath('unique_visitors', 2);
        $res->assertJsonPath('range.from', '2026-10-15')->assertJsonPath('range.to', '2026-10-21');
        $this->assertCount(7, $res->json('series'));
        $this->assertSame(['date' => '2026-10-19', 'clicks' => 1], $res->json('series.4'));
        $this->assertSame(['date' => '2026-10-20', 'clicks' => 0], $res->json('series.5'));
        $this->assertSame(['date' => '2026-10-21', 'clicks' => 2], $res->json('series.6'));
        $this->assertSame(
            [['referrer' => 'google.com', 'clicks' => 2], ['referrer' => 'Direct', 'clicks' => 1]],
            $res->json('top_referrers'),
        );
    }

    public function test_weekly_interval_groups_by_monday(): void
    {
        $link = Link::factory()->create(['slug' => 'demo']);
        $this->click($link, '2026-10-19 10:00:00'); // Monday
        $this->click($link, '2026-10-21 10:00:00'); // same week
        $this->click($link, '2026-10-14 10:00:00'); // previous week (Wed)

        $series = $this->getJson('/api/links/demo/analytics?days=14&interval=week')->json('series');

        $byDate = collect($series)->pluck('clicks', 'date');
        $this->assertSame(2, $byDate['2026-10-19']);
        $this->assertSame(1, $byDate['2026-10-12']);
    }

    public function test_link_analytics_ignores_other_links(): void
    {
        $a = Link::factory()->create(['slug' => 'aaa']);
        $b = Link::factory()->create(['slug' => 'bbb']);
        $this->click($a, '2026-10-21 09:00:00');
        $this->click($b, '2026-10-21 09:00:00');
        $this->click($b, '2026-10-21 09:30:00');

        $this->getJson('/api/links/aaa/analytics')->assertJsonPath('total_clicks', 1);
    }

    public function test_overview_has_top_links_ordered_by_clicks(): void
    {
        $a = Link::factory()->create(['slug' => 'aaa']);
        $b = Link::factory()->create(['slug' => 'bbb']);
        $this->click($a, '2026-10-21 09:00:00');
        foreach (range(1, 3) as $i) {
            $this->click($b, "2026-10-20 0{$i}:00:00");
        }

        $res = $this->getJson('/api/analytics')->assertOk()->assertJsonPath('total_clicks', 4);
        $this->assertSame(['bbb', 'aaa'], array_column($res->json('top_links'), 'slug'));
        $this->assertSame(3, $res->json('top_links.0.clicks'));
    }

    public function test_empty_link_returns_zeros_and_unknown_is_404(): void
    {
        Link::factory()->create(['slug' => 'empty']);

        $res = $this->getJson('/api/links/empty/analytics?days=3')->assertOk()->assertJsonPath('total_clicks', 0);
        $this->assertCount(3, $res->json('series'));
        $this->assertSame([], $res->json('top_referrers'));
        $this->getJson('/api/links/ghost/analytics')->assertNotFound();
    }

    public function test_invalid_params_are_rejected(): void
    {
        Link::factory()->create(['slug' => 'demo']);
        $this->getJson('/api/links/demo/analytics?days=0')->assertStatus(422);
        $this->getJson('/api/links/demo/analytics?days=9999')->assertStatus(422);
        $this->getJson('/api/links/demo/analytics?interval=year')->assertStatus(422);
    }
}
