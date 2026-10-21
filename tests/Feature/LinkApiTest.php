<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_link_with_random_slug(): void
    {
        $res = $this->postJson('/api/links', ['destination_url' => 'https://example.com/a']);

        $res->assertCreated()->assertJsonPath('destination_url', 'https://example.com/a');
        $this->assertMatchesRegularExpression('/^[a-z0-9]{6}$/', $res->json('slug'));
    }

    public function test_creates_link_with_custom_slug_lowercased(): void
    {
        $this->postJson('/api/links', ['destination_url' => 'https://example.com', 'slug' => 'My-Link_1'])
            ->assertCreated()->assertJsonPath('slug', 'my-link_1');
    }

    public function test_rejects_duplicate_slug_case_insensitively(): void
    {
        Link::factory()->create(['slug' => 'taken']);

        $this->postJson('/api/links', ['destination_url' => 'https://example.com', 'slug' => 'TAKEN'])
            ->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function test_rejects_reserved_and_malformed_slugs(): void
    {
        foreach (['api', 'UP', 'admin', 'ab', 'has space', 'bad/slug', str_repeat('a', 33), 'emoji😀x'] as $slug) {
            $this->postJson('/api/links', ['destination_url' => 'https://example.com', 'slug' => $slug])
                ->assertStatus(422)->assertJsonValidationErrors('slug');
        }
    }

    public function test_rejects_non_http_destinations(): void
    {
        foreach (['javascript:alert(1)', 'ftp://example.com', 'not a url', ''] as $url) {
            $this->postJson('/api/links', ['destination_url' => $url])
                ->assertStatus(422)->assertJsonValidationErrors('destination_url');
        }
    }

    public function test_short_url_redirects_and_logs_click(): void
    {
        $link = Link::factory()->create(['slug' => 'demo', 'destination_url' => 'https://example.com/x']);

        $this->withHeaders(['Referer' => 'https://news.ycombinator.com/item?id=1', 'User-Agent' => 'TestAgent/1.0'])
            ->get('/demo')
            ->assertRedirect('https://example.com/x')
            ->assertStatus(302);

        $this->assertSame(1, $link->fresh()->clicks_count);
        $this->assertDatabaseHas('link_clicks', [
            'link_id' => $link->id,
            'user_agent' => 'TestAgent/1.0',
            'referrer' => 'https://news.ycombinator.com/item?id=1',
        ]);
    }

    public function test_slug_lookup_is_case_insensitive_and_counts_each_visit(): void
    {
        $link = Link::factory()->create(['slug' => 'demo']);

        $this->get('/DEMO')->assertStatus(302);
        $this->get('/demo')->assertStatus(302);

        $this->assertSame(2, $link->fresh()->clicks_count);
        $this->assertSame(2, $link->clicks()->count());
    }

    public function test_unknown_slug_is_404_and_logs_nothing(): void
    {
        $this->get('/nope123')->assertNotFound();
        $this->assertDatabaseCount('link_clicks', 0);
    }

    public function test_legacy_api_redirect_still_works(): void
    {
        Link::factory()->create(['slug' => 'demo', 'destination_url' => 'https://example.com']);
        $this->get('/api/links/demo')->assertRedirect('https://example.com');
    }

    public function test_health_route_is_not_shadowed_by_slug_route(): void
    {
        $this->get('/up')->assertOk();
    }
}
