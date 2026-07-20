<?php

namespace Tests\Feature\Matches;

use App\Models\DatingPreference;
use App\Models\MatchTopic;
use App\Models\User;
use Database\Seeders\MatchTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_open_to_dating_defaults_true(): void
    {
        $user = User::factory()->create();

        $preference = DatingPreference::create([
            'user_id' => $user->id,
        ]);

        $this->assertTrue($preference->fresh()->is_open_to_dating);
    }

    public function test_match_topic_seeder_creates_six_fixed_topics(): void
    {
        (new MatchTopicSeeder())->run();

        $this->assertSame(6, MatchTopic::where('is_fixed', true)->whereNull('user_id')->count());

        foreach (['newest', 'local', 'friendship', 'long_term', 'marriage', 'open_to_dating'] as $slug) {
            $this->assertTrue(MatchTopic::where('slug', $slug)->where('is_fixed', true)->exists());
        }
    }
}
