<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\LinkClick;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Boundary and failure cases for the endpoints built in this block. */
class LinkEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_destination_is_required_and_must_be_a_string_url(): void
    {
        $this->postJson('/api/links', [])->assertStatus(422)->assertJsonValidationErrors('destination_url');
        $this->postJson('/api/links', ['destination_url' => ['https://a.com']])->assertStatus(422);
        $this->postJson('/api/links', ['destination_url' => 'not a url'])->assertStatus(422);
        $this->postJson('/api/links', ['destination_url' => 'ftp://example.com/file'])->assertStatus(422);
        $this->assertSame(0, Link::count());
    }

    public function test_destination_length_limit(): void
    {
        $long = 'https://example.com/'.str_repeat('a', 2048);
        $this->postJson('/api/links', ['destination_url' => $long])->assertStatus(422);

        $ok = 'https://example.com/'.str_repeat('a', 2048 - strlen('https://example.com/'));
        $this->postJson('/api/links', ['destination_url' => $ok])->assertCreated();
    }

    public function test_slug_length_boundaries(): void
    {
        $url = 'https://example.com';
        $this->postJson('/api/links', ['destination_url' => $url, 'slug' => 'ab'])->assertStatus(422);
        $this->postJson('/api/links', ['destination_url' => $url, 'slug' => 'abc'])->assertCreated();
        $this->postJson('/api/links', ['destination_url' => $url, 'slug' => str_repeat('a', 32)])->assertCreated();
        $this->postJson('/api/links', ['destination_url' => $url, 'slug' => str_repeat('b', 33)])->assertStatus(422);
    }

    public function test_slug_must_be_a_string_and_reserved_check_ignores_case(): void
    {
        $url = 'https://example.com';
        $this->postJson('/api/links', ['destination_url' => $url, 'slug' => ['abc']])->assertStatus(422);
        $this->postJson('/api/links', ['destination_url' => $url, 'slug' => 'API'])->assertStatus(422);
        $this->postJson('/api/links', ['destination_url' => $url, 'slug' => 'bad slug'])->assertStatus(422);
    }

    public function test_expiry_must_be_in_the_future(): void
    {
        $url = 'https://example.com';
        $this->postJson('/api/links', ['destination_url' => $url, 'expires_at' => now()->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors('expires_at');
        $this->postJson('/api/links', ['destination_url' => $url, 'expires_at' => now()->addMinute()->toIso8601String()])
            ->assertCreated();
    }

    public function test_link_flips_to_gone_exactly_when_time_passes_expiry(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00'));
        Link::factory()->create(['slug' => 'soon', 'expires_at' => now()->addMinutes(5)]);

        $this->get('/soon')->assertRedirect();
        $this->travel(6)->minutes();
        $this->get('/soon')->assertStatus(410);
        $this->getJson('/api/links/soon')->assertStatus(410);
    }

    public function test_clicks_before_expiry_still_show_in_analytics_afterwards(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00'));
        Link::factory()->create(['slug' => 'brief', 'expires_at' => now()->addHour()]);
        $this->get('/brief')->assertRedirect();
        $this->travel(2)->hours();
        $this->get('/brief')->assertStatus(410); // not counted

        $this->getJson('/api/links/brief/analytics?days=1')->assertOk()->assertJsonPath('total_clicks', 1);
    }

    public function test_redirect_stores_referrer_and_truncates_a_long_user_agent(): void
    {
        $link = Link::factory()->create(['slug' => 'trk']);

        $this->get('/trk', ['Referer' => 'https://www.reddit.com/r/x', 'User-Agent' => str_repeat('z', 500)])
            ->assertRedirect($link->destination_url);

        $click = LinkClick::firstOrFail();
        $this->assertSame('https://www.reddit.com/r/x', $click->referrer);
        $this->assertSame(255, strlen($click->user_agent));
        $this->assertSame(1, $link->fresh()->clicks_count);
    }

    public function test_uppercase_slug_redirects_and_counts_on_the_same_link(): void
    {
        $link = Link::factory()->create(['slug' => 'mixed']);

        $this->get('/MiXeD')->assertRedirect($link->destination_url);
        $this->assertSame(1, $link->fresh()->clicks_count);
    }

    public function test_listing_is_paginated_newest_first(): void
    {
        foreach (range(1, 25) as $i) {
            Link::factory()->create(['slug' => sprintf('link%02d', $i), 'created_at' => now()->subMinutes(30 - $i)]);
        }

        $res = $this->getJson('/api/links')->assertOk();

        $this->assertCount(20, $res->json('data'));
        $this->assertSame(25, $res->json('total'));
        $this->assertSame('link25', $res->json('data.0.slug'));
        $this->assertCount(5, $this->getJson('/api/links?page=2')->json('data'));
    }

    public function test_listing_with_no_links_is_an_empty_page(): void
    {
        $this->getJson('/api/links')->assertOk()->assertJsonPath('data', [])->assertJsonPath('total', 0);
    }

    public function test_overview_on_a_fresh_install_has_empty_lists_and_default_range(): void
    {
        $res = $this->getJson('/api/analytics')->assertOk()
            ->assertJsonPath('total_clicks', 0)
            ->assertJsonPath('unique_visitors', 0)
            ->assertJsonPath('range.days', 30)
            ->assertJsonPath('range.interval', 'day');

        $this->assertSame([], $res->json('top_links'));
        $this->assertSame([], $res->json('top_referrers'));
        $this->assertCount(30, $res->json('series'));
    }

    public function test_one_year_window_is_the_maximum(): void
    {
        $this->getJson('/api/analytics?days=365')->assertOk()->assertJsonCount(365, 'series');
        $this->getJson('/api/analytics?days=366')->assertStatus(422);
        $this->getJson('/api/analytics?days=abc')->assertStatus(422);
    }

    public function test_qr_encodes_the_short_url_and_is_cacheable(): void
    {
        Link::factory()->create(['slug' => 'qrx']);

        $res = $this->get('/api/links/QRX/qr')->assertOk();

        $this->assertStringContainsString('max-age=3600', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('viewBox="0 0 ', $res->getContent());
    }
}
