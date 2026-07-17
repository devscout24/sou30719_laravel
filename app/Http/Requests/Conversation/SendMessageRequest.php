<?php

namespace App\Http\Requests\Conversation;

use App\Http\Requests\BaseApiRequest;

class SendMessageRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'max:10240'],
        ];
    }
}
