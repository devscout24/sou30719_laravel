<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace' => $this->workspace?->slug,
            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => asset($this->user->avatar ?? 'user.png'),
            ],
            'topic' => $this->topic,
            'description' => $this->content,
            'images' => $this->images->map(fn ($image) => $image->full_url)->all(),
            'status' => $this->status,
            'total_likes' => (int) ($this->likes_count ?? $this->likes()->count()),
            'total_shares' => (int) ($this->shares_count ?? $this->shares()->count()),
            'is_liked' => (bool) ($this->is_liked ?? false),
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
