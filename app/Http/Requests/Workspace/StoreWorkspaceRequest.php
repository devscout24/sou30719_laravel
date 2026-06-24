<?php

namespace App\Http\Requests\Workspace;

use App\Http\Requests\BaseApiRequest;

class StoreWorkspaceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prompt' => ['required', 'string', 'max:255', 'unique:workspaces,prompt'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:workspaces,slug'],
            'is_supported' => ['boolean'],
            'status' => ['in:active,inactive'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
