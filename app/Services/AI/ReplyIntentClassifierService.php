<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Support\Facades\Log;

class ReplyIntentClassifierService
{
    protected const CONFIRM_WORDS = ['yes', 'yeah', 'yep', 'yup', 'sure', 'correct', 'confirm', 'ok', 'okay', 'affirmative', 'thats right'];
    protected const DECLINE_WORDS = ['no', 'nope', 'nah', 'incorrect', 'wrong', 'negative', 'not right', 'thats wrong'];

    protected const APPROVE_WORDS = ['approve', 'publish', 'post it', 'looks good', 'good to go', 'proceed', 'looks great', 'ship it'];
    protected const EDIT_WORDS = ['edit', 'change', 'modify', 'update', 'revise', 'fix', 'tweak'];
    protected const DELETE_WORDS = ['delete', 'remove', 'cancel', 'discard', 'scrap'];

    protected const MALE_WORDS = ['male', 'man', 'men', 'guy', 'guys', 'boy', 'boys'];
    protected const FEMALE_WORDS = ['female', 'woman', 'women', 'girl', 'girls', 'lady', 'ladies'];

    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Interpret a free-text reply to a yes/no confirmation question.
     *
     * @return 'yes'|'no'|null
     */
    public function classifyConfirmation(string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        $words = $this->normalize($text);

        if ($this->matchesAny($words, self::DECLINE_WORDS)) {
            return 'no';
        }

        if ($this->matchesAny($words, self::CONFIRM_WORDS)) {
            return 'yes';
        }

        return $this->aiClassify($text, ['yes', 'no']);
    }

    /**
     * Interpret a free-text reply to the post preview options (approve/edit/delete).
     *
     * @return 'approve'|'edit'|'delete'|null
     */
    public function classifyPreviewAction(string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        $words = $this->normalize($text);

        if ($this->matchesAny($words, self::APPROVE_WORDS)) {
            return 'approve';
        }

        if ($this->matchesAny($words, self::EDIT_WORDS)) {
            return 'edit';
        }

        if ($this->matchesAny($words, self::DELETE_WORDS)) {
            return 'delete';
        }

        return $this->aiClassify($text, ['approve', 'edit', 'delete']);
    }

    /**
     * Interpret a free-text reply naming a gender preference (Matches workspace).
     *
     * @return 'male'|'female'|null
     */
    public function classifyGender(string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        $words = $this->normalize($text);

        if ($this->matchesAny($words, self::FEMALE_WORDS)) {
            return 'female';
        }

        if ($this->matchesAny($words, self::MALE_WORDS)) {
            return 'male';
        }

        return $this->aiClassify($text, ['male', 'female']);
    }

    /**
     * @return string[]
     */
    protected function normalize(string $text): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($text)) ?? '';

        return array_values(array_filter(preg_split('/\s+/', trim($clean)) ?: []));
    }

    protected const NEGATION_WORDS = ['not', 'dont', 'cant', 'isnt', 'wasnt', 'never', 'no'];

    /**
     * @param  string[]  $words
     * @param  string[]  $vocabulary
     */
    protected function matchesAny(array $words, array $vocabulary): bool
    {
        $joined = ' ' . implode(' ', $words) . ' ';

        foreach ($vocabulary as $phrase) {
            $needle = ' ' . $phrase . ' ';
            $pos = strpos($joined, $needle);

            if ($pos === false) {
                continue;
            }

            // Skip matches immediately preceded by a negation word (e.g. "not sure").
            $preceding = explode(' ', trim(substr($joined, 0, $pos)));
            $lastWord = end($preceding);

            if (in_array($lastWord, self::NEGATION_WORDS, true)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  string[]  $labels
     */
    protected function aiClassify(string $text, array $labels): ?string
    {
        $messages = [
            [
                'role'    => 'system',
                'content' => 'Classify the user reply into exactly one of these labels: ' . implode(', ', $labels) . '. '
                    . 'Respond with strict JSON only, no prose: {"label": "<one of the labels>" or null}. '
                    . 'Use null only if the reply genuinely does not indicate any of the labels.',
            ],
            [
                'role'    => 'user',
                'content' => $text,
            ],
        ];

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Reply intent classification failed', ['error' => $e->getMessage()]);

            return null;
        }

        $decoded = json_decode($content, true);
        $label = $decoded['label'] ?? null;

        return in_array($label, $labels, true) ? $label : null;
    }
}
