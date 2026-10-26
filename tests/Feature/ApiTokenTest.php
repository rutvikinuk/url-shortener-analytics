<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_is_open_when_no_token_is_configured(): void
    {
        config(['shortener.api_token' => null]);

        $this->getJson('/api/links')->assertOk();
    }

    public function test_management_endpoints_require_the_token_when_configured(): void
    {
        config(['shortener.api_token' => 's3cret']);
        Link::create(['slug' => 'demo', 'destination_url' => 'https://example.com']);

        $this->getJson('/api/links')->assertStatus(401);
        $this->getJson('/api/links/demo')->assertStatus(401);
        $this->getJson('/api/analytics')->assertStatus(401);
        $this->getJson('/api/links/demo/analytics')->assertStatus(401);
        $this->postJson('/api/links', ['destination_url' => 'https://example.com'])->assertStatus(401);
        $this->assertDatabaseCount('links', 1);
    }

    public function test_wrong_token_is_rejected_and_right_token_works(): void
    {
        config(['shortener.api_token' => 's3cret']);

        $this->withToken('nope')->getJson('/api/links')->assertStatus(401);
        $this->withToken('s3cret')->getJson('/api/links')->assertOk();
        $this->withToken('s3cret')
            ->postJson('/api/links', ['destination_url' => 'https://example.com', 'slug' => 'tokened'])
            ->assertCreated();
    }

    public function test_redirect_and_qr_stay_public(): void
    {
        config(['shortener.api_token' => 's3cret']);
        Link::create(['slug' => 'demo', 'destination_url' => 'https://example.com']);

        $this->get('/demo')->assertRedirect('https://example.com');
        $this->get('/api/links/demo/qr')->assertOk();
    }
}
