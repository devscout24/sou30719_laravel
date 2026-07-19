<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MatchCriteriaService
{
    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Is this free-text preference specific enough to search with, or too
     * vague to act on (e.g. "good height" vs. "5'6\" or taller")?
     * Degrades to true (treat as concrete, proceed) on AI failure — a
     * transient outage must not block a user from getting matches.
     */
    public function isConcrete(string $criteria): bool
    {
        $messages = [
            ['role' => 'system', 'content' => $this->concreteSystemPrompt()],
            ['role' => 'user', 'content' => $criteria],
        ];

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Match criteria concreteness check failed', ['error' => $e->getMessage()]);

            return true;
        }

        $decoded = json_decode($content, true);

        return (bool) ($decoded['concrete'] ?? true);
    }

    /**
     * Rank a bounded list of candidates against free-text criteria in one
     * call (not one call per candidate). Degrades to an empty array on AI
     * failure — callers should fall back to an unranked candidate list.
     *
     * @param  Collection<int, \App\Models\User>  $candidates  each with datingProfile loaded
     * @return array<int, array{user_id: int, score: int, reason: string}>
     */
    public function rankCandidates(string $criteria, Collection $candidates): array
    {
        $profiles = $candidates->map(function ($candidate) {
            $profile = $candidate->datingProfile;

            return [
                'user_id' => $candidate->id,
                'height'  => $profile?->height,
                'about'   => $profile?->about ?? $profile?->about_me,
                'hobbies' => $profile?->hobbies,
            ];
        })->values()->all();

        $messages = [
            ['role' => 'system', 'content' => $this->rankSystemPrompt()],
            ['role' => 'user', 'content' => json_encode(['criteria' => $criteria, 'candidates' => $profiles])],
        ];

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Match candidate ranking failed', ['error' => $e->getMessage()]);

            return [];
        }

        $decoded  = json_decode($content, true);
        $rankings = (array) ($decoded['rankings'] ?? []);

        return array_values(array_filter(array_map(function ($ranking) {
            if (!is_array($ranking) || !isset($ranking['user_id'])) {
                return null;
            }

            return [
                'user_id' => (int) $ranking['user_id'],
                'score'   => max(0, min(100, (int) ($ranking['score'] ?? 0))),
                'reason'  => trim((string) ($ranking['reason'] ?? '')),
            ];
        }, $rankings)));
    }

    protected function concreteSystemPrompt(): string
    {
        return <<<'TEXT'
            You help a dating app determine whether a stated match preference is specific enough to search with.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"concrete": true|false}

            Rules:
            - true: the preference names a specific, actionable value (e.g. "5'6\" or taller", "at least 170cm", "loves hiking and camping").
            - false: the preference is vague with no actionable value (e.g. "good height", "nice", "attractive", "tall").
            TEXT;
    }

    protected function rankSystemPrompt(): string
    {
        return <<<'TEXT'
            You help a dating app rank candidate profiles against a user's stated preference.

            You are given a JSON object with "criteria" (the user's stated preference, free text) and
            "candidates" (an array of {user_id, height, about, hobbies} — any field may be null if unset).

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"rankings": [{"user_id": <int>, "score": <0-100 int>, "reason": "<short reason, 1 sentence>"}]}

            Rules:
            - Include every candidate you were given, even a poor fit (score them low, don't omit them).
            - "score" reflects how well the candidate's available fields match the stated criteria.
            - "reason" is a short, natural explanation of the score (e.g. "Height listed as 5'7\", matches your preference").
            - If a candidate's relevant fields are null/unset, score conservatively around 50 (unknown, not a mismatch) and say so in "reason".
            TEXT;
    }
}
