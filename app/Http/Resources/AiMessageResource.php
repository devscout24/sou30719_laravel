<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,        // message, card, post, pills
            'sender'      => $this->sender,      // user, ai
            'content'     => $this->decodedContent(),
            'attachments' => $this->attachments,
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
