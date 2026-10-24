<?php

namespace Tests\Unit;

use App\Models\Link;
use App\Rules\ValidSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class LinkModelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();
        parent::tearDown();
    }

    public function test_is_expired_handles_null_past_and_future(): void
    {
        $this->assertFalse(Link::factory()->make(['expires_at' => null])->isExpired());
        $this->assertFalse(Link::factory()->make(['expires_at' => now()->addSecond()])->isExpired());
        $this->assertTrue(Link::factory()->make(['expires_at' => now()->subSecond()])->isExpired());
    }

    public function test_is_reserved_ignores_case(): void
    {
        $this->assertTrue(Link::isReserved('API'));
        $this->assertTrue(Link::isReserved('Admin'));
        $this->assertFalse(Link::isReserved('my-link'));
    }

    public function test_generated_slug_skips_reserved_and_taken_values(): void
    {
        Link::factory()->create(['slug' => 'taken1']);
        $candidates = ['api', 'TAKEN1', 'fresh1'];
        Str::createRandomStringsUsing(function () use (&$candidates) {
            return array_shift($candidates);
        });

        $this->assertSame('fresh1', Link::generateUniqueSlug());
        $this->assertSame([], $candidates, 'all three candidates should have been tried');
    }

    public function test_generated_slug_is_lowercase_and_valid_by_default(): void
    {
        $slug = Link::generateUniqueSlug();

        $this->assertSame(6, strlen($slug));
        $this->assertMatchesRegularExpression('/^'.Link::SLUG_PATTERN.'$/', $slug);
        $this->assertSame(strtolower($slug), $slug);
    }

    public function test_short_url_uses_the_app_url(): void
    {
        $this->assertSame(url('/abc'), Link::factory()->make(['slug' => 'abc'])->shortUrl());
    }

    public function test_record_click_stores_request_data_and_bumps_counter(): void
    {
        $link = Link::factory()->create(['clicks_count' => 0]);
        $request = Request::create('/x', 'GET', [], [], [], [
            'REMOTE_ADDR' => '9.9.9.9',
            'HTTP_USER_AGENT' => str_repeat('u', 400),
            'HTTP_REFERER' => 'https://news.example/post',
        ]);

        $click = $link->recordClick($request);

        $this->assertSame('9.9.9.9', $click->ip);
        $this->assertSame(255, strlen($click->user_agent));
        $this->assertSame('https://news.example/post', $click->referrer);
        $this->assertSame(1, $link->fresh()->clicks_count);
        $this->assertSame(1, $link->clicks()->count());
    }

    public function test_record_click_without_referer_stores_null(): void
    {
        $link = Link::factory()->create();
        $click = $link->recordClick(Request::create('/x', 'GET'));

        $this->assertNull($click->referrer);
    }

    public function test_valid_slug_rule(): void
    {
        $passes = fn ($v) => Validator::make(['slug' => $v], ['slug' => [new ValidSlug]])->passes();

        foreach (['abc', 'a-b_c', 'A1B2', str_repeat('a', 32)] as $ok) {
            $this->assertTrue($passes($ok), "'$ok' should be valid");
        }
        foreach (['ab', str_repeat('a', 33), 'has space', 'dot.dot', 'ünï', 'api', 'UP', ['abc'], 123] as $bad) {
            $this->assertFalse($passes($bad), json_encode($bad).' should be invalid');
        }
    }
}
