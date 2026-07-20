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
     * and return polished long/short descriptions plus tags. An optional
     * topic hint (what the user said the post was about, before writing the
     * description) is passed along as extra context when present.
     *
     * @param  string[]  $imagePaths  Paths on the public disk.
     * @return array{topic: string, description: string, short_description: string, tags: string[]}
     */
    public function curate(string $description, array $imagePaths, ?string $topicHint = null): array
    {
        $text = filled($topicHint)
            ? "The user said this post is about: {$topicHint}.\n\n{$description}"
            : $description;

        $content = [['type' => 'text', 'text' => $text]];

        foreach (array_slice($imagePaths, 0, self::MAX_IMAGES) as $path) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $this->toDataUri($path)]];
        }

        $reply = $this->openAI->chat([
            ['role' => 'system', 'content' => $this->curateSystemPrompt()],
            ['role' => 'user', 'content' => $content],
        ], jsonMode: true);

        return $this->parseResult($reply);
    }

    /**
     * Apply a user's free-text edit instruction to an existing draft.
     *
     * @return array{topic: string, description: string, short_description: string, tags: string[]}
     */
    public function refine(string $topic, string $description, string $instruction): array
    {
        $reply = $this->openAI->chat([
            ['role' => 'system', 'content' => $this->refineSystemPrompt()],
            ['role' => 'user', 'content' => "Current topic: {$topic}\nCurrent description: {$description}\nInstruction: {$instruction}"],
        ], jsonMode: true);

        return $this->parseResult($reply);
    }

    /**
     * Generate an advertisement listing (Market Place workspace) from the
     * structured ad-form fields the frontend collected, plus an optional
     * user note and product/service image(s).
     *
     * @param  string[]  $imagePaths  Paths on the public disk.
     * @return array{topic: string, description: string, short_description: string, tags: string[]}
     */
    public function curateAd(
        string $adType,
        string $category,
        ?string $productUrl,
        ?float $discountPercentage,
        ?string $userNote,
        array $imagePaths
    ): array {
        $facts = "Listing type: {$adType}\nCategory: {$category}";

        if (filled($productUrl)) {
            $facts .= "\nProduct/service URL: {$productUrl}";
        }

        if ($discountPercentage !== null && $discountPercentage > 0) {
            $facts .= "\nDiscount: {$discountPercentage}%";
        }

        if (filled($userNote)) {
            $facts .= "\nSeller's notes: {$userNote}";
        }

        $content = [['type' => 'text', 'text' => $facts]];

        foreach (array_slice($imagePaths, 0, self::MAX_IMAGES) as $path) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $this->toDataUri($path)]];
        }

        $reply = $this->openAI->chat([
            ['role' => 'system', 'content' => $this->curateAdSystemPrompt()],
            ['role' => 'user', 'content' => $content],
        ], jsonMode: true);

        return $this->parseResult($reply);
    }

    /**
     * Generate a standalone post (no images) for an admin-created AI post.
     *
     * @return array{topic: string, description: string, short_description: string, tags: string[]}
     */
    public function generateAdminPost(string $theme): array
    {
        $reply = $this->openAI->chat([
            ['role' => 'system', 'content' => $this->adminPostSystemPrompt()],
            ['role' => 'user', 'content' => "Theme: {$theme}"],
        ], jsonMode: true);

        return $this->parseResult($reply);
    }

    protected function parseResult(string $reply): array
    {
        $decoded = json_decode($reply, true);

        if (!is_array($decoded) || empty($decoded['topic']) || empty($decoded['long_description'])) {
            throw new AIServiceException('AI returned an invalid response. Please try again.', 502);
        }

        return [
            'topic'             => trim((string) $decoded['topic']),
            'description'       => trim((string) $decoded['long_description']),
            'short_description' => trim((string) ($decoded['short_description'] ?? '')),
            'tags'              => array_values(array_filter(
                array_map('trim', (array) ($decoded['tags'] ?? []))
            )),
        ];
    }

    protected function toDataUri(string $path): string
    {
        $mime     = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
        $contents = Storage::disk('public')->get($path);

        return "data:{$mime};base64," . base64_encode($contents);
    }

    protected function curateSystemPrompt(): string
    {
        return <<<'TEXT'
            You are an assistant inside the "Social" workspace of an app that helps users publish social media posts.
            The user has provided a description and one or more images for their post.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {
              "topic": "...",
              "long_description": "...",
              "short_description": "...",
              "tags": ["tag1", "tag2", "tag3"]
            }

            Rules:
            - "topic" is a short label (1-3 words) summarizing what the post is about, based on the images and description together.
            - "long_description" is an improved, polished version of the user's description: keep their voice and meaning, fix grammar, make it engaging.
            - "short_description" is a 1-2 sentence preview/summary of the post for feed cards.
            - "tags" is an array of 3-10 relevant lowercase keywords or short phrases (e.g. "food", "travel", "sunset") derived from the content and images.
            TEXT;
    }

    protected function curateAdSystemPrompt(): string
    {
        return <<<'TEXT'
            You are an assistant inside the "Market Place" workspace of an app, writing an advertisement listing
            for a product or service the user is selling. You are given the listing type, category, and any
            optional URL, discount, seller notes, and image(s).

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {
              "topic": "...",
              "long_description": "...",
              "short_description": "...",
              "tags": ["tag1", "tag2", "tag3"]
            }

            Rules:
            - "topic" is a short product/service name (1-4 words) inferred from the image and any notes given.
            - "long_description" is a persuasive, well-written advertisement description (2-4 sentences) covering what's
              being offered, based on the image and the facts provided. Mention the discount naturally if one was given.
            - "short_description" is a 1-sentence preview for a marketplace card.
            - "tags" is an array of 3-8 relevant lowercase keywords derived from the category, type, and image.
            TEXT;
    }

    protected function refineSystemPrompt(): string
    {
        return <<<'TEXT'
            You are an assistant helping a user refine the draft of a social media post.
            You are given the current topic, current description, and an instruction describing how to change it.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {
              "topic": "...",
              "long_description": "...",
              "short_description": "...",
              "tags": ["tag1", "tag2", "tag3"]
            }

            Apply the instruction to the description. Only change the topic if the instruction clearly changes the subject matter.
            Keep "short_description" as a 1-2 sentence preview of the updated post.
            Keep "tags" as 3-10 relevant lowercase keywords.
            TEXT;
    }

    protected function adminPostSystemPrompt(): string
    {
        return <<<'TEXT'
            You are an AI content creator for a social platform. Generate an engaging social media post based on the given theme.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {
              "topic": "...",
              "long_description": "...",
              "short_description": "...",
              "tags": ["tag1", "tag2", "tag3"]
            }

            Rules:
            - "topic" is a short label (1-3 words) for the post.
            - "long_description" is a well-written, engaging post of 100-200 words.
            - "short_description" is a 1-2 sentence preview.
            - "tags" is an array of 5-10 relevant lowercase keywords.
            TEXT;
    }
}
