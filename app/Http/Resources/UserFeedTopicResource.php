<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFeedTopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'icon'       => $this->icon,
            'is_fixed'   => (bool) $this->is_fixed,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
