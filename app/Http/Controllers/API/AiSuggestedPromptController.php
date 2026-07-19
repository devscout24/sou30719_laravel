<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AiSuggestedPrompt;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AiSuggestedPromptController extends Controller
{
    use ApiResponse;

    /**
     * Starter chips shown before the user's first message on an AI chat screen
     * (distinct from "pills", which are in-conversation reply options).
     *
     * ?context=feed_search            -> for POST /feed/ai-search and /feed/ai-chat
     * ?context=workspace_conversation -> for POST /conversations (+ /messages)
     * ?workspace_id=1                 -> (workspace_conversation only) also include
     *                                     prompts scoped to that workspace
     * No ?context -> all prompts grouped by context.
     */
    public function index(Request $request)
    {
        $query = AiSuggestedPrompt::query()->active()->orderBy('sort_order');

        if ($context = $request->query('context')) {
            $query->forContext($context);
        }

        if ($workspaceId = $request->query('workspace_id')) {
            $query->where(function ($q) use ($workspaceId) {
                $q->whereNull('workspace_id')->orWhere('workspace_id', $workspaceId);
            });
        }

        $prompts = $query->get(['id', 'context', 'workspace_id', 'label', 'prompt', 'sort_order']);

        $data = $context
            ? $prompts->map(fn ($p) => $this->transform($p))->values()
            : $prompts->groupBy('context')->map(fn ($group) => $group->map(fn ($p) => $this->transform($p))->values());

        return $this->success($data, 'Suggested prompts fetched successfully');
    }

    protected function transform(AiSuggestedPrompt $prompt): array
    {
        return [
            'id'           => $prompt->id,
            'label'        => $prompt->label ?: $prompt->prompt,
            'prompt'       => $prompt->prompt,
            'workspace_id' => $prompt->workspace_id,
        ];
    }
}
