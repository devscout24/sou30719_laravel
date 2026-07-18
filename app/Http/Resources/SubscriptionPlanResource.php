<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'slug'                    => $this->slug,
            'billing_cycle'           => $this->billing_cycle,
            'price'                   => $this->price,
            'formatted_price'         => $this->price > 0
                ? '$' . number_format($this->price, 2) . '/' . ($this->billing_cycle === 'yearly' ? 'Year' : 'Month')
                : 'Free',
            'max_posts_per_day'       => $this->max_posts_per_day,
            'max_matches_per_day'     => $this->max_matches_per_day,
            'max_ai_requests_per_day' => $this->max_ai_requests_per_day,
            'is_current'              => (bool) $this->is_current,
        ];
    }
}
