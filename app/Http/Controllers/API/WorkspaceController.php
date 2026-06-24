<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Traits\ApiResponse;

class WorkspaceController extends Controller
{
    use ApiResponse;

    /**
     * List active workspaces (used to render workspace cards / prompt pills).
     */
    public function index()
    {
        $workspaces = Workspace::active()->orderBy('sort_order')->get();

        return $this->success(WorkspaceResource::collection($workspaces), 'Workspaces fetched successfully');
    }

    /**
     * Admin: create a new workspace.
     */
    public function store(StoreWorkspaceRequest $request)
    {
        $workspace = Workspace::create($request->validated());

        return $this->success(new WorkspaceResource($workspace), 'Workspace created successfully', 201);
    }
}
