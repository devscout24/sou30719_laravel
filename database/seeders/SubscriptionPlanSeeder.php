<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * The four pricing tiers from the pricing-page mockup (Starter/Plus/Pro/Premium),
     * each seeded twice — monthly and yearly billing. The mockup only shows monthly
     * prices; yearly is 10x monthly (2 months free), a placeholder pending real numbers.
     * max_ai_requests_per_day has no numeric value in the mockup (just "limits apply" /
     * "increased" / "liberal") — placeholder quotas here, tune via the admin panel.
     * The mockup's per-feature checklist (Event/Marketplace/Interest Hub/Personal
     * Courier limits) has no backing column on subscription_plans — not seeded.
     */
    public function run(): void
    {
        $tiers = [
            [
                'name'                    => 'Starter',
                'monthly_price'           => 0,
                'yearly_price'            => 0,
                'max_posts_per_day'       => 5,
                'max_matches_per_day'     => 5,
                'max_ai_requests_per_day' => 10,
            ],
            [
                'name'                    => 'Plus',
                'monthly_price'           => 9.99,
                'yearly_price'            => 99.90,
                'max_posts_per_day'       => 10,
                'max_matches_per_day'     => 10,
                'max_ai_requests_per_day' => 30,
            ],
            [
                'name'                    => 'Pro',
                'monthly_price'           => 29.99,
                'yearly_price'            => 299.90,
                'max_posts_per_day'       => 15,
                'max_matches_per_day'     => 15,
                'max_ai_requests_per_day' => 60,
            ],
            [
                'name'                    => 'Premium',
                'monthly_price'           => 59.99,
                'yearly_price'            => 599.90,
                'max_posts_per_day'       => 30,
                'max_matches_per_day'     => 30,
                'max_ai_requests_per_day' => null, // liberal / unlimited
            ],
        ];

        $seededSlugs = [];

        foreach ($tiers as $tier) {
            $baseSlug = Str::slug($tier['name']);

            foreach (['monthly', 'yearly'] as $cycle) {
                $slug = "{$baseSlug}-{$cycle}";

                SubscriptionPlan::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name'                    => $tier['name'],
                        'billing_cycle'           => $cycle,
                        'price'                   => $cycle === 'monthly' ? $tier['monthly_price'] : $tier['yearly_price'],
                        'max_posts_per_day'       => $tier['max_posts_per_day'],
                        'max_matches_per_day'     => $tier['max_matches_per_day'],
                        'max_ai_requests_per_day' => $tier['max_ai_requests_per_day'],
                        'is_active'               => true,
                    ]
                );

                $seededSlugs[] = $slug;
            }
        }

        // Retire any plan no longer part of the canonical set above.
        SubscriptionPlan::whereNotIn('slug', $seededSlugs)->get()->each->delete();
    }
}
