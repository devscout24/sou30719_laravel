<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\BaseApiRequest;

class SendChatMessageRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:6'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (blank($this->input('message')) && !$this->hasFile('attachments')) {
                $validator->errors()->add('message', 'Please provide a message or at least one attachment.');
            }
        });
    }
}
