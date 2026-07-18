<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    public function test_sidebar_reflects_the_reorganized_sections(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        // Main — User Management moved here from Settings
        $response->assertSee(route('admin.user.lists'), false);

        // Content & Community — new coming-soon placeholders
        $response->assertSee(route('admin.coming-soon', 'marketplace-ad'), false);
        $response->assertSee(route('admin.coming-soon', 'event'), false);
        $response->assertSee(route('admin.coming-soon', 'interest-hub'), false);
        $response->assertSee(route('admin.coming-soon', 'courier'), false);
        $response->assertSee(route('admin.coming-soon', 'cms'), false);

        // Billing — Subscriptions + Subscription Plan grouped into one dropdown
        $response->assertSee('Subscription Management');

        // Support — Post Reports renamed + moved, Dynamic Pages moved from Settings
        $response->assertSee('Report Management');
        $response->assertSee(route('dynamic.pages'), false);

        // General Setting — renamed items + new Admin Management stub
        $response->assertSee('Credential Management');
        $response->assertSee(route('admin.coming-soon', 'admin-management'), false);

        // Dead theme-template links removed
        $response->assertDontSee('Demo 01');
        $response->assertDontSee('index.html', false);
    }
}
