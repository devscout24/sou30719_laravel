<?php

namespace Tests\Feature\Marketplace;

use App\Models\MarketplaceTopic;
use Database\Seeders\MarketplaceTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_topic_seeder_creates_five_fixed_topics(): void
    {
        (new MarketplaceTopicSeeder())->run();

        $this->assertSame(5, MarketplaceTopic::where('is_fixed', true)->whereNull('user_id')->count());

        foreach (['products', 'services', 'cosmetics', 'electronics', 'food'] as $slug) {
            $this->assertTrue(MarketplaceTopic::where('slug', $slug)->where('is_fixed', true)->exists());
        }
    }
}
