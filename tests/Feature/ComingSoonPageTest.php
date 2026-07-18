<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ComingSoonPageTest extends TestCase
{
    public function test_authenticated_user_sees_placeholder_for_known_feature(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.coming-soon', 'marketplace-ad'));

        $response->assertOk();
        $response->assertSee('Marketplace/Ad');
        $response->assertSee('Coming soon');
    }

    public function test_unknown_feature_slug_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.coming-soon', 'not-a-real-feature'));

        $response->assertNotFound();
    }
}
