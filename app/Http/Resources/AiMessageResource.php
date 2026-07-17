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
            'content'     => $this->resolvedContent(),
            'attachments' => $this->resolvedAttachments(),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Decode the message content, resolving any embedded image paths (e.g. the
     * post preview card's images) into full URLs with domain.
     */
    protected function resolvedContent(): mixed
    {
        $content = $this->decodedContent();

        if ($this->type === 'post' && is_array($content) && !empty($content['images'])) {
            $content['images'] = array_map(
                fn (array $image) => ['path' => $this->toUrl($image['path'])],
                $content['images']
            );
        }

        return $content;
    }

    /**
     * Resolve stored attachment paths into full URLs with domain.
     */
    protected function resolvedAttachments(): ?array
    {
        if (empty($this->attachments)) {
            return $this->attachments;
        }

        return array_map(fn (string $path) => $this->toUrl($path), $this->attachments);
    }

    /**
     * Build a full domain URL from a stored path, whether it's already a full
     * URL, already prefixed with "storage/" (legacy rows), or a bare relative
     * path — avoids double-prefixing like ".../storage/storage/...".
     */
    protected function toUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#^storage/#', '', $path);

        return asset('storage/' . $path);
    }
}
