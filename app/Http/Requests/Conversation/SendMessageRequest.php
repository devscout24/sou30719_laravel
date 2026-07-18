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

            // Market Place workspace ad-form fields — only relevant while
            // collecting an advertisement draft, harmless elsewhere.
            'ad_type' => ['nullable', 'string', 'in:product,service'],
            'category' => ['nullable', 'string', 'max:100'],
            'product_url' => ['nullable', 'string', 'url', 'max:2000'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'show_sale_badge' => ['nullable', 'boolean'],
        ];
    }
}
