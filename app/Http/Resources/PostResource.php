<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'slug'       => $this->slug,
            'workspace'  => $this->workspace?->slug,
            'author'     => [
                'id'     => $this->user->id,
                'username' => $this->user->username,
                'name'   => $this->user->name,
                'avatar' => asset($this->user->avatar ?? 'user.png'),
            ],
            'type'              => $this->type,       // regular | event | ad
            'created_by'        => $this->created_by, // user | ai
            'topic'             => $this->topic,
            'title'             => $this->title,
            'description'       => $this->content,
            'short_description' => $this->short_description,
            'image_description' => $this->image_description,
            'tags'              => $this->tags ?? [],
            'images'            => $this->images->map(fn ($img) => $img->full_url)->all(),

            // Event-specific
            'event_date'        => $this->event_date?->toISOString(),
            'event_location'    => $this->event_location,

            // Ad-specific
            'price'             => $this->price,
            'location'          => $this->location,
            'category'          => $this->category,

            'status'       => $this->status,
            'total_likes'  => (int) ($this->likes_count  ?? $this->likes()->count()),
            'total_shares' => (int) ($this->shares_count ?? $this->shares()->count()),
            'is_liked'     => (bool) ($this->is_liked ?? false),
            'published_at' => $this->published_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
