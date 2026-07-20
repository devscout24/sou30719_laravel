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
     * vague to act on (e.g. "good height" vs. "5'6\" or taller")? When
     * vague, also returns a short, tailored example suggestion (based on
     * what the user actually said) so the follow-up question tells them
     * what kind of detail would help, instead of a generic prompt.
     * Degrades to concrete=true (proceed) on AI failure — a transient
     * outage must not block a user from getting matches.
     *
     * @return array{concrete: bool, suggestion: ?string}
     */
    public function assessCriteria(string $criteria): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->concreteSystemPrompt()],
            ['role' => 'user', 'content' => $criteria],
        ];

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Match criteria concreteness check failed', ['error' => $e->getMessage()]);

            return ['concrete' => true, 'suggestion' => null];
        }

        $decoded    = json_decode($content, true);
        $concrete   = (bool) ($decoded['concrete'] ?? true);
        $suggestion = trim((string) ($decoded['suggestion'] ?? ''));

        return [
            'concrete'   => $concrete,
            'suggestion' => $suggestion !== '' ? $suggestion : null,
        ];
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
                'user_id'            => $candidate->id,
                'height'             => $profile?->height,
                'occupation'         => $profile?->occupation,
                'education'          => $profile?->education,
                'lifestyle_habits'   => $profile?->lifestyle_habits,
                'body_type'          => $profile?->body_type,
                'ethnicity'          => $profile?->ethnicity,
                'religion'           => $profile?->religious_beliefs ?? $profile?->religion,
                'languages'          => $profile?->languages,
                'location'           => $profile?->dating_location ?? $profile?->city,
                'about'              => $profile?->about ?? $profile?->about_me,
                'hobbies'            => $profile?->hobbies,
                'personality_traits' => $profile?->personality_traits,
                'pet_preference'     => $profile?->pet_preference,
                'political_views'    => $profile?->political_views,
                'family_plans'       => $profile?->family_plans,
                'children_status'    => $profile?->children_status,
                'relationship_goal'  => $profile?->relationship_goal,
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
            You help a dating app determine whether a stated match preference is specific enough to search with,
            and if not, suggest what kind of detail would help.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"concrete": true|false, "suggestion": "<short example, only when concrete is false, otherwise empty string>"}

            Rules:
            - true: the preference names a specific, actionable value (e.g. "5'6\" or taller", "at least 170cm", "loves hiking and camping").
            - false: the preference is vague with no actionable value (e.g. "good height", "nice", "attractive", "tall").
            - When false, "suggestion" is a short, natural phrase (max 15 words) giving a concrete example tailored
              to what they said — e.g. for "good height" suggest an exact height range like "5'6\" or taller"; for
              "nice personality" suggest a specific trait like "outgoing" or "family-oriented". No markdown, no
              surrounding quotes around the example itself.
            TEXT;
    }

    protected function rankSystemPrompt(): string
    {
        return <<<'TEXT'
            You help a dating app rank candidate profiles against a user's stated preference.

            You are given a JSON object with "criteria" (the user's stated preference, free text) and
            "candidates" (an array of profile objects — any field may be null if unset). Each candidate
            object has: user_id, height, occupation, education, lifestyle_habits, body_type, ethnicity,
            religion, languages, location, about, hobbies, personality_traits, pet_preference,
            political_views, family_plans, children_status, relationship_goal.

            Respond with ONLY strict JSON (no markdown, no commentary) in exactly this shape:
            {"rankings": [{"user_id": <int>, "score": <0-100 int>, "reason": "<short reason, 1 sentence>"}]}

            Rules:
            - Include every candidate you were given, even a poor fit (score them low, don't omit them).
            - Only judge candidates against dimensions the criteria actually mentions — ignore fields the
              criteria says nothing about (e.g. if criteria only mentions height, don't penalize for religion).
            - "score" reflects how many of the mentioned dimensions the candidate's available fields satisfy —
              the more matched dimensions, the higher the score.
            - If a candidate's data clearly contradicts a stated dimension (e.g. criteria says "non-smoker"
              and lifestyle_habits says they smoke), score that dimension low.
            - If a field relevant to a stated dimension is null/unset, score that dimension conservatively
              around 50 (unknown, not a mismatch) rather than penalizing the candidate for missing data.
            - "reason" is a short, natural explanation referencing the specific dimensions that drove the
              score (e.g. "5'9\" matches your height preference, and their hobbies include hiking").
            TEXT;
    }
}
