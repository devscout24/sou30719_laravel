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
     * When the resolved workspace is Matches, also extracts any stated gender
     * preference and any other stated preference as free text (e.g. "I'm looking
     * for a good height woman" -> gender "female", criteria "good height"), so the
     * Matches flow doesn't have to ask "male or female?" when it's already known.
     * Both are null whenever the resolved workspace isn't Matches.
     *
     * @param  Collection<int, Workspace>  $workspaces
     * @param  array<int, array{role: string, content: string}>  $history  prior turns, oldest first
     * @return array{workspace: ?Workspace, reply: string, match_gender: ?string, match_criteria: ?string}
     */
    public function interpret(string $text, Collection $workspaces, array $history = []): array
    {
        $options = $workspaces->map(fn (Workspace $workspace) => [
            'id'          => $workspace->id,
            'title'       => $workspace->title,
            'description' => $workspace->description,
            'prompt'      => $workspace->prompt,
        ])->values()->all();

        $matchesWorkspaceId = $workspaces->firstWhere('slug', Workspace::SLUG_MATCHES)?->id;

        $system = 'You are a friendly assistant chatting with a user inside a social app, helping them figure out '
            . "what they'd like to do. Reply naturally to whatever they say — greetings, small talk, or a stated "
            . "goal — like a real conversation, not a form. The available workspaces (things you can help them "
            . 'do) are listed below as JSON; each needs a description and at least one image once you know which '
            . 'one fits. If nothing said so far points to a specific workspace, keep the conversation going: ask '
            . "what they're looking to do or planning, in your own words, without repeating yourself if you've "
            . "already asked. Never invent a workspace_id that isn't in the list.\n\n"
            . "If the workspace that fits is the Matches workspace (id {$matchesWorkspaceId}), also extract any "
            . "stated gender preference and any other stated preference as free text — e.g. \"I'm looking for a "
            . "good height woman\" gives gender \"female\", criteria \"good height\"; \"match with him or her\" "
            . "gives gender \"both\", criteria null. Use null for either field when not stated, and leave both "
            . "null whenever the fitting workspace isn't Matches.\n\n"
            . 'Respond with strict JSON only, no prose outside the JSON: '
            . '{"workspace_id": <int or null>, "confidence": <float 0-1>, "reply": "<your natural reply, 1-3 sentences>", '
            . '"match_gender": <"male"|"female"|"both"|null>, "match_criteria": <string or null>}. '
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

            return ['workspace' => null, 'reply' => self::FALLBACK_REPLY, 'match_gender' => null, 'match_criteria' => null];
        }

        $decoded = json_decode($content, true);
        $workspaceId = $decoded['workspace_id'] ?? null;
        $confidence  = (float) ($decoded['confidence'] ?? 0);
        $reply       = trim((string) ($decoded['reply'] ?? '')) ?: self::FALLBACK_REPLY;

        $workspace = ($workspaceId && $confidence >= self::CONFIDENCE_THRESHOLD)
            ? $workspaces->firstWhere('id', $workspaceId)
            : null;

        $matchGender = null;
        $matchCriteria = null;

        if ($workspace && $workspace->slug === Workspace::SLUG_MATCHES) {
            $rawGender   = $decoded['match_gender'] ?? null;
            $matchGender = in_array($rawGender, ['male', 'female', 'both'], true) ? $rawGender : null;

            $rawCriteria   = $decoded['match_criteria'] ?? null;
            $matchCriteria = filled($rawCriteria) ? trim((string) $rawCriteria) : null;
        }

        return ['workspace' => $workspace, 'reply' => $reply, 'match_gender' => $matchGender, 'match_criteria' => $matchCriteria];
    }
}
