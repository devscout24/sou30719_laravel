<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'slug'         => $this->slug,
            'workspace_id' => $this->workspace_id,
            'workspace'    => $this->when($this->relationLoaded('workspace') && $this->workspace, fn () => [
                'id'    => $this->workspace->id,
                'title' => $this->workspace->title,
                'slug'  => $this->workspace->slug,
            ]),
            'status'     => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'messages'   => AiMessageResource::collection($this->whenLoaded('messages')),
            'post'       => $this->when($this->relationLoaded('post') && $this->post, fn () => [
                'id'   => $this->post->id,
                'slug' => $this->post->slug,
            ]),
        ];
    }
}
