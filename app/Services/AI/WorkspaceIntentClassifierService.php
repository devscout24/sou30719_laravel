<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WorkspaceIntentClassifierService
{
    protected const CONFIDENCE_THRESHOLD = 0.6;

    public function __construct(protected OpenAIService $openai)
    {
    }

    /**
     * Ask the AI which workspace (if any) best matches the free-text intent of the message.
     *
     * @param  Collection<int, Workspace>  $workspaces
     */
    public function classify(string $text, Collection $workspaces): ?Workspace
    {
        if ($workspaces->isEmpty()) {
            return null;
        }

        $options = $workspaces->map(fn (Workspace $workspace) => [
            'id'          => $workspace->id,
            'title'       => $workspace->title,
            'description' => $workspace->description,
            'prompt'      => $workspace->prompt,
        ])->values()->all();

        $messages = [
            [
                'role'    => 'system',
                'content' => 'You match a user chat message to the workspace they most likely intend to use. '
                    . 'Respond with strict JSON only, no prose: {"workspace_id": <int or null>, "confidence": <float 0-1>}. '
                    . 'Return workspace_id null if no workspace clearly matches the user intent.',
            ],
            [
                'role'    => 'user',
                'content' => json_encode([
                    'message'    => $text,
                    'workspaces' => $options,
                ]),
            ],
        ];

        try {
            $content = $this->openai->chat($messages, jsonMode: true);
        } catch (AIServiceException $e) {
            Log::warning('Workspace intent classification failed', ['error' => $e->getMessage()]);

            return null;
        }

        $decoded = json_decode($content, true);
        $workspaceId = $decoded['workspace_id'] ?? null;
        $confidence  = (float) ($decoded['confidence'] ?? 0);

        if (!$workspaceId || $confidence < self::CONFIDENCE_THRESHOLD) {
            return null;
        }

        return $workspaces->firstWhere('id', $workspaceId);
    }
}
