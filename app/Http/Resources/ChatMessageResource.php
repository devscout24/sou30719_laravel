<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'message_type' => $this->message_type,
            'message' => $this->message,
            'attachments' => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'url' => $attachment->full_url,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
            ]),
            'is_read' => (bool) $this->is_read,
            'is_mine' => $this->sender_id === Auth::guard('api')->id(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
