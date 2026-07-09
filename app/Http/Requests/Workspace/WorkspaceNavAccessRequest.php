<?php

namespace App\Http\Requests\Workspace;

use App\Enums\NavSection;
use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class WorkspaceNavAccessRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'nav_keys' => ['required', 'array', 'min:1'],
            'nav_keys.*' => ['string', Rule::in(NavSection::values())],
        ];
    }
}
