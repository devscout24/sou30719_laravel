<?php

namespace App\Http\Requests\Post;

use App\Http\Requests\BaseApiRequest;

class ReportPostRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'reason'      => ['required', 'string', 'in:spam,harassment,hate_speech,misinformation,nudity,violence,other'],
            'description' => ['nullable', 'string', 'max:1000'],
            'block_user'  => ['sometimes', 'boolean'],
        ];
    }
}
