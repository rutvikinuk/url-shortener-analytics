<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Services\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiryAndQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_link_with_future_expiry(): void
    {
        $res = $this->postJson('/api/links', [
            'destination_url' => 'https://example.com',
            'slug' => 'promo',
            'expires_at' => now()->addDay()->toIso8601String(),
        ]);

        $res->assertCreated();
        $this->assertNotNull(Link::where('slug', 'promo')->first()->expires_at);
    }

    public function test_rejects_expiry_in_the_past_or_garbage(): void
    {
        $this->postJson('/api/links', ['destination_url' => 'https://example.com', 'expires_at' => now()->subHour()->toIso8601String()])
            ->assertUnprocessable()->assertJsonValidationErrors('expires_at');
        $this->postJson('/api/links', ['destination_url' => 'https://example.com', 'expires_at' => 'not-a-date'])
            ->assertUnprocessable()->assertJsonValidationErrors('expires_at');
    }

    public function test_link_without_expiry_never_expires(): void
    {
        $this->assertFalse(Link::factory()->create(['expires_at' => null])->isExpired());
    }

    public function test_active_link_redirects_before_expiry(): void
    {
        Link::factory()->create(['slug' => 'soon', 'destination_url' => 'https://example.com/x', 'expires_at' => now()->addMinute()]);

        $this->get('/soon')->assertRedirect('https://example.com/x');
    }

    public function test_expired_link_returns_410_and_logs_no_click(): void
    {
        $link = Link::factory()->create(['slug' => 'old', 'expires_at' => now()->subMinute()]);

        $this->get('/old')->assertStatus(410);
        $this->getJson('/api/links/old')->assertStatus(410);

        $this->assertSame(0, $link->fresh()->clicks_count);
        $this->assertSame(0, $link->clicks()->count());
    }

    public function test_qr_endpoint_returns_svg(): void
    {
        Link::factory()->create(['slug' => 'qrme']);

        $res = $this->get('/api/links/qrme/qr');

        $res->assertOk();
        $this->assertStringContainsString('image/svg+xml', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $res->getContent());
        $this->assertStringContainsString('<path', $res->getContent());
    }

    public function test_qr_for_unknown_or_expired_link(): void
    {
        $this->get('/api/links/nope/qr')->assertNotFound();
        Link::factory()->create(['slug' => 'gone', 'expires_at' => now()->subDay()]);
        $this->get('/api/links/gone/qr')->assertStatus(410);
    }

    public function test_qr_encoder_structure(): void
    {
        $m = QrCode::matrix('https://example.com');   // 19 bytes -> version 2 -> 25x25

        $this->assertCount(25, $m);
        $this->assertCount(25, $m[0]);
        // top-left finder pattern: dark ring, light ring, dark 3x3 centre
        $this->assertTrue($m[0][0] && $m[0][6] && $m[6][0] && $m[6][6]);
        $this->assertFalse($m[1][1] || $m[1][5]);
        $this->assertTrue($m[3][3]);
        // timing pattern alternates along row 6
        $this->assertTrue($m[6][8]);
        $this->assertFalse($m[6][9]);
        $this->assertSame($m, QrCode::matrix('https://example.com')); // deterministic
    }

    public function test_qr_encoder_grows_and_rejects_oversized_text(): void
    {
        $this->assertCount(21, QrCode::matrix('x'));
        $this->assertCount(57, QrCode::matrix(str_repeat('a', 213)));
        $this->expectException(\InvalidArgumentException::class);
        QrCode::matrix(str_repeat('a', 214));
    }
}
