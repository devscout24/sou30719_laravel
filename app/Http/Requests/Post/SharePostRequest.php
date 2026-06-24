<?php

namespace App\Http\Requests\Post;

use App\Http\Requests\BaseApiRequest;

class SharePostRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:facebook,twitter,instagram,linkedin,whatsapp,telegram'],
        ];
    }
}
