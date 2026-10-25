<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function unsafeDestinations(): array
    {
        return [
            'credentials' => ['https://bank.com@evil.example/login'],
            'password' => ['https://user:pass@example.com/'],
            'localhost' => ['http://localhost/admin'],
            'subdomain of localhost' => ['http://app.localhost/'],
            'loopback ip' => ['http://127.0.0.1:8080/'],
            'private 10.x' => ['http://10.0.0.5/'],
            'private 192.168.x' => ['http://192.168.1.1/'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 loopback' => ['http://[::1]/'],
            'internal tld' => ['http://db.internal/'],
            'itself' => ['http://localhost:8000/abc'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'file scheme' => ['file:///etc/passwd'],
            'space' => ['https://example.com/a b'],
            'newline' => ["https://example.com/a\r\nSet-Cookie: x=1"],
            'too long' => ['https://example.com/'.str_repeat('a', 2100)],
        ];
    }

    #[DataProvider('unsafeDestinations')]
    public function test_unsafe_destinations_are_rejected(string $url): void
    {
        $this->postJson('/api/links', ['destination_url' => $url])
            ->assertStatus(422)
            ->assertJsonValidationErrors('destination_url');

        $this->assertDatabaseCount('links', 0);
    }

    public function test_a_normal_https_destination_still_works(): void
    {
        $this->postJson('/api/links', ['destination_url' => 'https://example.com/path?q=1#top'])
            ->assertCreated();
    }

    public function test_array_and_non_string_input_does_not_crash(): void
    {
        $this->postJson('/api/links', ['destination_url' => ['https://example.com']])->assertStatus(422);
        $this->postJson('/api/links', ['destination_url' => 'https://example.com', 'slug' => ['x']])->assertStatus(422);
        $this->postJson('/api/links', ['destination_url' => 'https://example.com', 'expires_at' => ['x']])->assertStatus(422);
    }

    public function test_sql_injection_in_slug_or_query_is_inert(): void
    {
        Link::create(['slug' => 'safe-one', 'destination_url' => 'https://example.com']);

        $this->postJson('/api/links', ['destination_url' => 'https://example.com', 'slug' => "x'; DROP TABLE links;--"])
            ->assertStatus(422);
        $this->getJson('/api/links/'.urlencode("safe-one' OR '1'='1"))->assertNotFound();
        $this->getJson('/api/analytics?days='.urlencode('1; DROP TABLE links'))->assertStatus(422);

        $this->assertDatabaseCount('links', 1);
    }

    public function test_mass_assignment_cannot_set_the_click_counter(): void
    {
        $response = $this->postJson('/api/links', [
            'destination_url' => 'https://example.com',
            'clicks_count' => 9999,
            'id' => 12345,
        ])->assertCreated();

        $link = Link::where('slug', $response->json('slug'))->firstOrFail();
        $this->assertSame(0, (int) $link->clicks_count);
        $this->assertNotSame(12345, $link->id);
    }

    public function test_creating_links_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/links', ['destination_url' => "https://example.com/{$i}"])->assertCreated();
        }

        $this->postJson('/api/links', ['destination_url' => 'https://example.com/11'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // A different visitor is not affected.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson('/api/links', ['destination_url' => 'https://example.com/other'])
            ->assertCreated();
    }

    public function test_redirects_are_rate_limited(): void
    {
        Link::create(['slug' => 'busy', 'destination_url' => 'https://example.com']);

        for ($i = 0; $i < 300; $i++) {
            $this->get('/busy')->assertRedirect();
        }

        $this->get('/busy')->assertStatus(429);
    }

    public function test_api_reads_are_rate_limited(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/links')->assertOk();
        }

        $this->getJson('/api/links')->assertStatus(429);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->getJson('/api/links')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_qr_svg_is_locked_down(): void
    {
        Link::create(['slug' => 'qrtest', 'destination_url' => 'https://example.com']);

        $response = $this->get('/api/links/qrtest/qr')->assertOk();
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_validation_errors_do_not_leak_internals(): void
    {
        config(['app.debug' => false]); // production setting

        $body = $this->postJson('/api/links', [])->assertStatus(422)->getContent();

        $this->assertStringNotContainsString('vendor/', $body);
        $this->assertStringNotContainsString('SQLSTATE', $body);
    }

    public function test_unknown_slug_returns_a_plain_404_without_a_stack_trace(): void
    {
        config(['app.debug' => false]); // production setting; APP_DEBUG=true would show a trace by design

        $body = $this->getJson('/api/links/nope-nope')->assertNotFound()->getContent();

        $this->assertStringNotContainsString('vendor/', $body);
        $this->assertStringNotContainsString('trace', $body);
    }

    public function test_env_files_and_keys_are_not_tracked_by_git(): void
    {
        $ignore = file_get_contents(base_path('.gitignore'));
        $this->assertStringContainsString('.env', $ignore);
        $this->assertStringContainsString('/storage/*.key', $ignore);
        $this->assertSame('', config('services.postmark.key') ?? '');
    }
}
