<?php

namespace App\Http\Requests\Matches;

use App\Http\Requests\BaseApiRequest;

class StoreMatchTopicRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }
}
