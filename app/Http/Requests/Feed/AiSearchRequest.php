<?php

namespace App\Http\Requests\Feed;

use App\Http\Requests\BaseApiRequest;

class AiSearchRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'prompt'   => ['required', 'string', 'min:3', 'max:500'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
