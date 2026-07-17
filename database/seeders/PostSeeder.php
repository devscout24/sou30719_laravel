<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PostSeeder extends Seeder
{
    /**
     * Sample images already present under public/storage/posts, reused
     * cyclically so the seeder doesn't depend on network access.
     */
    protected array $sampleImages = [
        'posts/4d860f27-8bec-4989-aa47-7a7de9b750d5.png',
        'posts/5c152836-4f60-4748-8fe4-9e0140d432cb.png',
        'posts/d1Rqcrf93HwHZ1zLRO4stoiHWxYcGHDaYjOK6FSX.png',
        'posts/M4XaMAQqI7uZlVG4OKD2dErTKOZAcnkRzUi1Rlsq.png',
    ];

    public function run(): void
    {
        $admin = User::where('email', 'admin@admin.com')->first();

        if (!$admin) {
            $this->command?->warn('PostSeeder: admin user not found, skipping. Run UserSeeder first.');
            return;
        }

        $workspaceId = Workspace::where('slug', 'social_post')->value('id');

        foreach ($this->posts() as $index => $data) {
            $post = Post::firstOrCreate(
                ['user_id' => $admin->id, 'topic' => $data['topic']],
                [
                    'workspace_id'       => $workspaceId,
                    'type'               => $data['type'] ?? 'regular',
                    'created_by'         => 'ai',
                    'title'              => $data['title'] ?? null,
                    'content'            => $data['content'],
                    'short_description'  => $data['short_description'],
                    'image_description'  => $data['image_description'] ?? null,
                    'tags'               => $data['tags'] ?? [],
                    'location'           => $data['location'] ?? null,
                    'event_date'         => $data['event_date'] ?? null,
                    'event_location'     => $data['event_location'] ?? null,
                    'visibility'         => 'public',
                    'status'             => 'published',
                    'published_at'       => $data['published_at'],
                ]
            );

            if ($post->wasRecentlyCreated && !empty($data['with_image'])) {
                PostImage::create([
                    'post_id'    => $post->id,
                    'image_path' => $this->sampleImages[$index % count($this->sampleImages)],
                    'sort_order' => 0,
                ]);
            }
        }
    }

    protected function posts(): array
    {
        $now = Carbon::now();

        return [
            [
                'topic'              => 'Making the first move without the pressure',
                'content'            => 'Breaking the ice doesn\'t have to be scary. A simple, genuine question about something on their profile beats any pickup line. Curiosity always wins over cleverness.',
                'short_description'  => 'A friendly reminder that a genuine question beats a rehearsed line every time.',
                'tags'               => ['dating-tips', 'icebreaker', 'friendship'],
                'with_image'         => true,
                'image_description'  => 'Two coffee cups on a cafe table with soft morning light.',
                'published_at'       => $now->copy()->subMinutes(20),
            ],
            [
                'topic'              => 'Weekend hiking meetups are trending in your area',
                'content'            => 'More members are planning outdoor meetups this weekend. Group hikes are a low-pressure way to meet new people while doing something active. Check the Local tab to see who\'s nearby.',
                'short_description'  => 'Outdoor group meetups are picking up this weekend — a relaxed way to meet people nearby.',
                'tags'               => ['local', 'outdoors', 'trending'],
                'location'           => 'Community Trailhead',
                'with_image'         => true,
                'image_description'  => 'A scenic hiking trail winding through green hills.',
                'published_at'       => $now->copy()->subHours(2),
            ],
            [
                'topic'              => 'Five questions that spark real conversation',
                'content'            => "1. What's something you're excited about right now?\n2. What does a perfect Sunday look like for you?\n3. What's a small thing that always makes you smile?\n4. Where's somewhere you'd love to travel next?\n5. What are you learning at the moment?",
                'short_description'  => 'Skip the small talk — these five prompts lead to conversations worth having.',
                'tags'               => ['conversation-starters', 'dating-tips'],
                'published_at'       => $now->copy()->subHours(5),
            ],
            [
                'topic'              => 'Summer Olympics watch party — who\'s in?',
                'content'            => 'The Summer Games are almost here! We\'re curating watch-party meetups for members who love sports. Add "olympics" to your interests to get matched with fellow athletes and fans.',
                'short_description'  => 'Sports fans, this one\'s for you — Olympics-themed meetups are being curated now.',
                'tags'               => ['olympics', 'sports', 'athlete', 'games'],
                'type'               => 'event',
                'event_date'         => $now->copy()->addDays(14),
                'event_location'     => 'Downtown Sports Bar',
                'with_image'         => true,
                'image_description'  => 'A group cheering while watching a sports match on a big screen.',
                'published_at'       => $now->copy()->subHours(8),
            ],
            [
                'topic'              => 'How to write a bio that actually gets replies',
                'content'            => 'Skip the "ask me anything" and the long list of adjectives. Mention one specific hobby, one fun fact, and one thing you\'re looking for. Specificity is what makes people want to reach out.',
                'short_description'  => 'Specific beats generic — three small tweaks that make your bio easier to reply to.',
                'tags'               => ['profile-tips', 'dating-tips'],
                'published_at'       => $now->copy()->subHours(12),
            ],
            [
                'topic'              => 'Friendship first: not every match needs to be romantic',
                'content'            => 'Some of the best connections on the app start as friendships. If you\'re not feeling a romantic spark but you vibe with someone, there\'s nothing wrong with keeping it platonic.',
                'short_description'  => 'A reminder that great connections don\'t always have to be romantic ones.',
                'tags'               => ['friendship', 'community'],
                'published_at'       => $now->copy()->subHours(18),
            ],
            [
                'topic'              => 'Board game night, every Thursday',
                'content'            => 'Looking for a casual, low-key way to meet people? Our community board game nights run every Thursday evening. No pressure, just good games and good company.',
                'short_description'  => 'A casual weekly meetup for anyone who\'d rather play a game than make small talk.',
                'tags'               => ['local', 'friendship', 'community'],
                'type'               => 'event',
                'event_date'         => $now->copy()->addDays(3),
                'event_location'     => 'The Game Lounge',
                'with_image'         => true,
                'image_description'  => 'A table set up with board games and dice.',
                'published_at'       => $now->copy()->subDay(),
            ],
            [
                'topic'              => 'Red flags vs. just different: a quick gut-check',
                'content'            => 'Not every mismatch is a red flag — sometimes it\'s just a different pace or love language. Before writing someone off, ask yourself if it\'s a values gap or a compatibility gap. They\'re not the same thing.',
                'short_description'  => 'Not every disagreement is a red flag — a quick way to tell the difference.',
                'tags'               => ['dating-tips', 'trending'],
                'published_at'       => $now->copy()->subDays(1)->subHours(4),
            ],
            [
                'topic'              => 'Photo tips: what actually performs well',
                'content'            => 'Profiles with one clear face photo, one full-body photo, and one photo doing a hobby you love tend to get the most engagement. Group photos as your main picture usually backfire — people aren\'t sure which one is you.',
                'short_description'  => 'The three-photo formula that tends to perform best on profiles.',
                'tags'               => ['profile-tips'],
                'with_image'         => true,
                'image_description'  => 'A phone displaying a photo gallery grid.',
                'published_at'       => $now->copy()->subDays(2),
            ],
            [
                'topic'              => 'Sunset paddleboarding meetup this Saturday',
                'content'            => 'Grab a board and join a laid-back group session on the lake this Saturday evening. All experience levels welcome — it\'s more about the company than the workout.',
                'short_description'  => 'A relaxed lakeside meetup for anyone who wants to catch the sunset on the water.',
                'tags'               => ['local', 'outdoors', 'trending'],
                'type'               => 'event',
                'event_date'         => $now->copy()->addDays(5),
                'event_location'     => 'Lakeside Pier',
                'published_at'       => $now->copy()->subDays(2)->subHours(6),
            ],
            [
                'topic'              => 'Marathon training partners wanted',
                'content'            => 'Training for a fall marathon and want an accountability partner? Several members are looking for running buddies of all paces. Add "running" to your interests to find them.',
                'short_description'  => 'Looking for a running buddy for marathon season? You\'re not the only one.',
                'tags'               => ['olympics', 'sports', 'local'],
                'published_at'       => $now->copy()->subDays(3),
            ],
            [
                'topic'              => 'The etiquette of ending a chat gracefully',
                'content'            => 'If a conversation isn\'t going anywhere, a short and kind message beats ghosting every time. Something like "Really appreciated chatting, but I don\'t think we\'re a match — wishing you luck!" goes a long way.',
                'short_description'  => 'A kind exit is always better than silence — here\'s a simple way to do it.',
                'tags'               => ['dating-tips', 'community'],
                'published_at'       => $now->copy()->subDays(4),
            ],
            [
                'topic'              => 'Cooking class meetup: pasta from scratch',
                'content'            => 'Learn to make pasta by hand at our community cooking meetup. It\'s a fun, hands-on way to meet people while picking up a new skill — and yes, you get to eat what you make.',
                'short_description'  => 'Hands-on pasta-making meetup — a tasty way to meet new people.',
                'tags'               => ['local', 'friendship'],
                'type'               => 'event',
                'event_date'         => $now->copy()->addDays(9),
                'event_location'     => 'Community Kitchen Studio',
                'with_image'         => true,
                'image_description'  => 'Fresh pasta being rolled out on a floured wooden counter.',
                'published_at'       => $now->copy()->subDays(5),
            ],
            [
                'topic'              => 'Why "what are you looking for" is worth asking early',
                'content'            => 'Being upfront about intentions — whether that\'s something casual, something serious, or just new friends — saves everyone time and leads to better matches down the line.',
                'short_description'  => 'A little honesty early on goes a long way toward better matches.',
                'tags'               => ['dating-tips'],
                'published_at'       => $now->copy()->subDays(6),
            ],
        ];
    }
}
