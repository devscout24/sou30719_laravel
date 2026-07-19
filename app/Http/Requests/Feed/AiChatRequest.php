<?php

namespace App\Http\Requests\Feed;

use App\Http\Requests\BaseApiRequest;

class AiChatRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'message'           => ['required', 'string', 'min:1', 'max:500'],
            'per_page'          => ['nullable', 'integer', 'min:1', 'max:50'],
            'history'           => ['nullable', 'array', 'max:20'],
            'history.*.role'    => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1000'],
        ];
    }
}
