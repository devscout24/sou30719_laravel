<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Support\Facades\Storage;

class PostCuratorService
{
    protected const MAX_IMAGES = 6;

    public function __construct(protected OpenAIService $openAI)
    {
    }

    /**
     * Analyze the user's description + uploaded images, detect the topic,
     * and return a polished description. Images themselves are never altered.
     *
     * @param  string[]  $imagePaths  Paths on the public disk.
     * @return array{topic: string, description: string}
     */
    public function curate(string $description, array $imagePaths): array
    {
        $content = [['type' => 'text', 'text' => $description]];

        foreach (array_slice($imagePaths, 0, self::MAX_IMAGES) as $path) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $this->toDataUri($path)]];
        }

        $reply = $this->openAI->chat([
            ['role' => 'system', 'content' => $this->curateSystemPrompt()],
            ['role' => 'user', 'content' => $content],
        ], jsonMode: true);

        return $this->parseTopicAndDescription($reply);
    }

    /**
     * Apply a user's free-text edit instruction to an existing draft.
     *
     * @return array{topic: string, description: string}
     */
    public function refine(string $topic, string $description, string $instruction): array
    {
        $reply = $this->openAI->chat([
            ['role' => 'system', 'content' => $this->refineSystemPrompt()],
            ['role' => 'user', 'content' => "Current topic: {$topic}\nCurrent description: {$description}\nInstruction: {$instruction}"],
        ], jsonMode: true);

        return $this->parseTopicAndDescription($reply);
    }

    protected function parseTopicAndDescription(string $reply): array
    {
        $decoded = json_decode($reply, true);

        if (!is_array($decoded) || empty($decoded['topic']) || empty($decoded['description'])) {
            throw new AIServiceException('AI returned an invalid response. Please try again.', 502);
        }

        return [
            'topic' => trim((string) $decoded['topic']),
            'description' => trim((string) $decoded['description']),
        ];
    }

    protected function toDataUri(string $path): string
    {
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
        $contents = Storage::disk('public')->get($path);

        return "data:{$mime};base64," . base64_encode($contents);
    }

    protected function curateSystemPrompt(): string
    {
        return <<<'TEXT'
            You are an assistant inside the "Social" workspace of an app that helps users publish social media posts.
            The user has provided a description and one or more images for their post.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"topic": "...", "description": "..."}

            Rules:
            - "topic" is a short label (1-3 words) summarizing what the post is about, based on the images and description together.
            - "description" is an improved, polished version of the user's description: keep their voice and meaning, fix grammar, make it engaging.
            TEXT;
    }

    protected function refineSystemPrompt(): string
    {
        return <<<'TEXT'
            You are an assistant helping a user refine the draft of a social media post.
            You are given the current topic, current description, and an instruction describing how to change it (e.g. "make it more professional", "add emojis", "shorten it").

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"topic": "...", "description": "..."}

            Apply the instruction to the description. Only change the topic if the instruction clearly changes the subject matter.
            TEXT;
    }
}
