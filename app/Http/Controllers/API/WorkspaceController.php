<?php

namespace App\Http\Controllers\Api;

use App\Enums\NavSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\WorkspaceNavAccessRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    use ApiResponse;

    /**
     * List active workspaces (used to render workspace cards / prompt pills).
     */
    public function index()
    {
        $workspaces = Workspace::active()->with('navPermissions')->orderBy('sort_order')->get();

        return $this->success(WorkspaceResource::collection($workspaces), 'Workspaces fetched successfully');
    }

    /**
     * Basic info for a single workspace, including which navbar sections it has access to.
     */
    public function show(Workspace $workspace)
    {
        return $this->success(new WorkspaceResource($workspace->load('navPermissions')), 'Workspace basic info fetched successfully');
    }

    /**
     * Admin: create a new workspace. Defaults to full access across all fixed nav sections.
     */
    public function store(StoreWorkspaceRequest $request)
    {
        $workspace = DB::transaction(function () use ($request) {
            $workspace = Workspace::create($request->validated());

            $workspace->navPermissions()->createMany(
                collect(NavSection::values())->map(fn (string $key) => ['nav_key' => $key])->all()
            );

            return $workspace;
        });

        return $this->success(new WorkspaceResource($workspace->load('navPermissions')), 'Workspace created successfully', 201);
    }

    /**
     * Admin: grant this workspace access to one or more navbar sections.
     */
    public function grantNavAccess(WorkspaceNavAccessRequest $request, Workspace $workspace)
    {
        foreach ($request->validated('nav_keys') as $navKey) {
            $workspace->navPermissions()->firstOrCreate(['nav_key' => $navKey]);
        }

        return $this->success(['nav_access' => $workspace->fresh('navPermissions')->navAccessMap()], 'Navigation access granted successfully');
    }

    /**
     * Admin: revoke this workspace's access to one or more navbar sections.
     */
    public function revokeNavAccess(WorkspaceNavAccessRequest $request, Workspace $workspace)
    {
        $workspace->navPermissions()->whereIn('nav_key', $request->validated('nav_keys'))->delete();

        return $this->success(['nav_access' => $workspace->fresh('navPermissions')->navAccessMap()], 'Navigation access revoked successfully');
    }
}
