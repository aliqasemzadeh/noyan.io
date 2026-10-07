<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\ShortLink;
use App\Models\User;
use App\Support\ShortLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ShortLinkRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_link_redirects_and_increments_hits(): void
    {
        Config::set('short-link.short_url', 'https://short.test');
        Config::set('short-link.code_length', 5);

        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();

        $destination = 'https://app.test/invoices/public/1?signature=abc';

        $url = app(ShortLinkService::class)->create($destination, $business);

        $this->assertStringStartsWith('https://short.test/i/', $url);

        $code = basename(parse_url($url, PHP_URL_PATH));

        $this->assertDatabaseHas('short_links', [
            'code' => $code,
            'business_id' => $business->id,
            'destination' => $destination,
            'hits' => 0,
        ]);

        $this->get(route('short-links.redirect', ['code' => $code]))
            ->assertRedirect($destination);

        $this->assertSame(1, ShortLink::query()->where('code', $code)->value('hits'));
    }

    public function test_unknown_short_link_returns_not_found(): void
    {
        $this->get(route('short-links.redirect', ['code' => 'nope1']))
            ->assertNotFound();
    }

    public function test_expired_short_link_returns_not_found(): void
    {
        $shortLink = ShortLink::query()->create([
            'code' => 'exp01',
            'destination' => 'https://example.com',
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('short-links.redirect', ['code' => $shortLink->code]))
            ->assertNotFound();
    }
}
