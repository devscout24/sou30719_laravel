<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseApiRequest;

class GenerateAiPostRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'theme'      => ['required', 'string', 'min:3', 'max:200'],
            'post_type'  => ['nullable', 'string', 'in:regular,event,ad'],
            'visibility' => ['nullable', 'string', 'in:public,friends,private'],
        ];
    }
}
