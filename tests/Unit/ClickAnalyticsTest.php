<?php

namespace Tests\Unit;

use App\Models\Link;
use App\Models\LinkClick;
use App\Services\ClickAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function click(Link $link, string $when, ?string $ref = null, string $ip = '1.1.1.1'): void
    {
        LinkClick::factory()->create(['link_id' => $link->id, 'created_at' => $when, 'referrer' => $ref, 'ip' => $ip]);
    }

    private function day(string $d): CarbonImmutable
    {
        return CarbonImmutable::parse($d);
    }

    public function test_totals_on_empty_table_are_zero(): void
    {
        $this->assertSame(
            ['total_clicks' => 0, 'unique_visitors' => 0],
            (new ClickAnalytics)->totals($this->day('2026-10-01')),
        );
    }

    public function test_totals_count_distinct_ips_and_respect_the_start_boundary(): void
    {
        $link = Link::factory()->create();
        $this->click($link, '2026-10-10 00:00:00', null, '1.1.1.1'); // exactly at start: included
        $this->click($link, '2026-10-10 05:00:00', null, '1.1.1.1');
        $this->click($link, '2026-10-11 05:00:00', null, '2.2.2.2');
        $this->click($link, '2026-10-09 23:59:59', null, '3.3.3.3'); // one second too early

        $this->assertSame(
            ['total_clicks' => 3, 'unique_visitors' => 2],
            (new ClickAnalytics)->totals($this->day('2026-10-10')),
        );
    }

    public function test_scoping_to_a_link_ignores_the_others(): void
    {
        $a = Link::factory()->create();
        $b = Link::factory()->create();
        $this->click($a, '2026-10-10 10:00:00');
        $this->click($b, '2026-10-10 10:00:00');
        $this->click($b, '2026-10-10 11:00:00');

        $this->assertSame(2, (new ClickAnalytics($b))->totals($this->day('2026-10-01'))['total_clicks']);
        $this->assertSame(3, (new ClickAnalytics)->totals($this->day('2026-10-01'))['total_clicks']);
    }

    public function test_time_series_for_a_single_day_range(): void
    {
        $link = Link::factory()->create();
        $this->click($link, '2026-10-10 10:00:00');

        $series = (new ClickAnalytics)->timeSeries($this->day('2026-10-10'), $this->day('2026-10-10 23:00:00'));

        $this->assertSame([['date' => '2026-10-10', 'clicks' => 1]], $series);
    }

    public function test_time_series_is_zero_filled_and_sorted(): void
    {
        $link = Link::factory()->create();
        $this->click($link, '2026-10-12 10:00:00');
        $this->click($link, '2026-10-10 10:00:00');

        $series = (new ClickAnalytics)->timeSeries($this->day('2026-10-10'), $this->day('2026-10-13'));

        $this->assertSame(['2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13'], array_column($series, 'date'));
        $this->assertSame([1, 0, 1, 0], array_column($series, 'clicks'));
    }

    public function test_weekly_series_starts_on_the_monday_before_a_mid_week_start(): void
    {
        $link = Link::factory()->create();
        $this->click($link, '2026-10-14 10:00:00'); // Wed, week of Mon 12 Oct
        $this->click($link, '2026-10-18 10:00:00'); // Sun, same week
        $this->click($link, '2026-10-19 10:00:00'); // Mon, next week

        // 14 Oct 2026 is a Wednesday.
        $series = (new ClickAnalytics)->timeSeries($this->day('2026-10-14'), $this->day('2026-10-20'), 'week');

        $this->assertSame(
            [['date' => '2026-10-12', 'clicks' => 2], ['date' => '2026-10-19', 'clicks' => 1]],
            $series,
        );
    }

    public function test_referrers_merge_hosts_ignore_www_and_case_and_fall_back_to_direct(): void
    {
        $link = Link::factory()->create();
        $this->click($link, '2026-10-10 10:00:00', 'https://www.Google.com/search?q=a');
        $this->click($link, '2026-10-10 10:01:00', 'https://google.com/other');
        $this->click($link, '2026-10-10 10:01:30', 'http://GOOGLE.com:8080/x');
        $this->click($link, '2026-10-10 10:02:00', 'not a url at all');
        $this->click($link, '2026-10-10 10:03:00', null);
        $this->click($link, '2026-10-10 10:04:00', 'https://t.co/x');

        $rows = (new ClickAnalytics)->topReferrers($this->day('2026-10-01'));

        $this->assertSame(['referrer' => 'google.com', 'clicks' => 3], $rows[0]);
        $this->assertSame(['referrer' => 'Direct', 'clicks' => 2], $rows[1]);
        $this->assertSame(['referrer' => 't.co', 'clicks' => 1], $rows[2]);
    }

    public function test_referrer_limit_keeps_the_biggest(): void
    {
        $link = Link::factory()->create();
        foreach (['a.com' => 3, 'b.com' => 2, 'c.com' => 1] as $host => $n) {
            foreach (range(1, $n) as $i) {
                $this->click($link, "2026-10-10 10:0{$i}:00", "https://$host/");
            }
        }

        $rows = (new ClickAnalytics)->topReferrers($this->day('2026-10-01'), 2);

        $this->assertSame(['a.com', 'b.com'], array_column($rows, 'referrer'));
    }

    public function test_top_links_respects_window_and_limit(): void
    {
        $a = Link::factory()->create(['slug' => 'aaa']);
        $b = Link::factory()->create(['slug' => 'bbb']);
        $c = Link::factory()->create(['slug' => 'ccc']);
        foreach (range(1, 5) as $i) { // old clicks must not count
            $this->click($a, "2026-08-0{$i} 10:00:00");
        }
        $this->click($b, '2026-10-10 10:00:00');
        $this->click($b, '2026-10-10 11:00:00');
        $this->click($c, '2026-10-10 12:00:00');

        $rows = (new ClickAnalytics)->topLinks($this->day('2026-10-01'), 1);

        $this->assertCount(1, $rows);
        $this->assertSame('bbb', $rows[0]['slug']);
        $this->assertSame(2, $rows[0]['clicks']);
        $this->assertSame(['bbb', 'ccc'], array_column((new ClickAnalytics)->topLinks($this->day('2026-10-01')), 'slug'));
    }
}
