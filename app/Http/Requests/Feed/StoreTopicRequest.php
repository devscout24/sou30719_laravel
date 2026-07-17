<?php

namespace App\Http\Requests\Feed;

use App\Http\Requests\BaseApiRequest;

class StoreTopicRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }
}
