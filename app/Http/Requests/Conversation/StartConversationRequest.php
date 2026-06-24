<?php

namespace App\Http\Requests\Conversation;

use App\Http\Requests\BaseApiRequest;

class StartConversationRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
        ];
    }
}
