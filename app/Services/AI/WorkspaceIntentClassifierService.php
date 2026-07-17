<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WorkspaceIntentClassifierService
{
    protected const CONFIDENCE_THRESHOLD = 0.6;
    protected const FALLBACK_REPLY = "I couldn't quite tell what you're looking to do. Could you be more specific, or choose one of the options below?";

    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Have a natural back-and-forth with the user while working out which workspace
     * (if any) matches their intent. Always returns a conversational reply, even
     * when no workspace is confidently identified yet.
     *
     * @param  Collection<int, Workspace>  $workspaces
     * @param  array<int, array{role: string, content: string}>  $history  prior turns, oldest first
     * @return array{workspace: ?Workspace, reply: string}
     */
    public function interpret(string $text, Collection $workspaces, array $history = []): array
    {
        $options = $workspaces->map(fn (Workspace $workspace) => [
            'id'          => $workspace->id,
            'title'       => $workspace->title,
            'description' => $workspace->description,
            'prompt'      => $workspace->prompt,
        ])->values()->all();

        $system = 'You are a friendly assistant chatting with a user inside a social app, helping them figure out '
            . "what they'd like to do. Reply naturally to whatever they say — greetings, small talk, or a stated "
            . "goal — like a real conversation, not a form. The available workspaces (things you can help them "
            . 'do) are listed below as JSON; each needs a description and at least one image once you know which '
            . 'one fits. If nothing said so far points to a specific workspace, keep the conversation going: ask '
            . "what they're looking to do or planning, in your own words, without repeating yourself if you've "
            . "already asked. Never invent a workspace_id that isn't in the list.\n\n"
            . 'Respond with strict JSON only, no prose outside the JSON: '
            . '{"workspace_id": <int or null>, "confidence": <float 0-1>, "reply": "<your natural reply, 1-3 sentences>"}. '
            . "Use workspace_id null and confidence 0 if you're not yet confident which workspace fits.\n\n"
            . 'Workspaces: ' . json_encode($options);

        $messages = array_merge(
            [['role' => 'system', 'content' => $system]],
            $history,
            [['role' => 'user', 'content' => $text]],
        );

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Workspace intent interpretation failed', ['error' => $e->getMessage()]);

            return ['workspace' => null, 'reply' => self::FALLBACK_REPLY];
        }

        $decoded = json_decode($content, true);
        $workspaceId = $decoded['workspace_id'] ?? null;
        $confidence  = (float) ($decoded['confidence'] ?? 0);
        $reply       = trim((string) ($decoded['reply'] ?? '')) ?: self::FALLBACK_REPLY;

        $workspace = ($workspaceId && $confidence >= self::CONFIDENCE_THRESHOLD)
            ? $workspaces->firstWhere('id', $workspaceId)
            : null;

        return ['workspace' => $workspace, 'reply' => $reply];
    }
}
