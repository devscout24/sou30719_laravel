<?php

namespace App\Http\Requests\Marketplace;

use App\Http\Requests\BaseApiRequest;

class StoreMarketplaceTopicRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }
}
