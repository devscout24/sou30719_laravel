<?php

namespace App\Http\Controllers\Web\Backend;

use App\Enums\NavSection;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkspaceController extends Controller
{
    public function index()
    {
        $workspaces = Workspace::withCount('posts')->orderBy('sort_order')->orderBy('title')->get();

        return view('backend.layouts.workspaces.index', compact('workspaces'));
    }

    public function create()
    {
        $navSections = NavSection::values();

        return view('backend.layouts.workspaces.create', compact('navSections'));
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'prompt'       => 'required|string|max:255|unique:workspaces,prompt',
            'slug'         => 'required|alpha_dash|max:255|unique:workspaces,slug',
            'is_supported' => 'nullable|boolean',
            'status'       => 'required|in:active,inactive',
            'sort_order'   => 'nullable|integer|min:0',
            'nav_keys'     => 'nullable|array',
            'nav_keys.*'   => 'string|in:' . implode(',', NavSection::values()),
        ]);

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first())->withInput();
        }

        DB::transaction(function () use ($request) {
            $workspace = Workspace::create([
                'title'        => $request->title,
                'description'  => $request->description,
                'prompt'       => $request->prompt,
                'slug'         => $request->slug,
                'is_supported' => $request->boolean('is_supported'),
                'status'       => $request->status,
                'sort_order'   => $request->sort_order ?? 0,
            ]);

            $navKeys = $request->input('nav_keys', []);

            if (!empty($navKeys)) {
                $workspace->navPermissions()->createMany(
                    collect($navKeys)->map(fn (string $key) => ['nav_key' => $key])->all()
                );
            }
        });

        return redirect()->route('admin.workspaces.index')->with('success', 'Workspace created successfully');
    }

    public function edit(Workspace $workspace)
    {
        $navSections = NavSection::values();
        $grantedNavKeys = $workspace->navPermissions()->pluck('nav_key')->all();

        return view('backend.layouts.workspaces.edit', compact('workspace', 'navSections', 'grantedNavKeys'));
    }

    public function update(Request $request, Workspace $workspace)
    {
        $validation = Validator::make($request->all(), [
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'prompt'       => 'required|string|max:255|unique:workspaces,prompt,' . $workspace->id,
            'slug'         => 'required|alpha_dash|max:255|unique:workspaces,slug,' . $workspace->id,
            'is_supported' => 'nullable|boolean',
            'status'       => 'required|in:active,inactive',
            'sort_order'   => 'nullable|integer|min:0',
            'nav_keys'     => 'nullable|array',
            'nav_keys.*'   => 'string|in:' . implode(',', NavSection::values()),
        ]);

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first())->withInput();
        }

        DB::transaction(function () use ($request, $workspace) {
            $workspace->update([
                'title'        => $request->title,
                'description'  => $request->description,
                'prompt'       => $request->prompt,
                'slug'         => $request->slug,
                'is_supported' => $request->boolean('is_supported'),
                'status'       => $request->status,
                'sort_order'   => $request->sort_order ?? 0,
            ]);

            $navKeys = $request->input('nav_keys', []);
            $workspace->navPermissions()->whereNotIn('nav_key', $navKeys)->delete();

            foreach ($navKeys as $key) {
                $workspace->navPermissions()->firstOrCreate(['nav_key' => $key]);
            }
        });

        return redirect()->route('admin.workspaces.index')->with('success', 'Workspace updated successfully');
    }

    public function destroy(Workspace $workspace)
    {
        $workspace->delete();

        return redirect()->route('admin.workspaces.index')->with('success', 'Workspace deleted successfully');
    }

    public function updateStatus(Request $request, Workspace $workspace)
    {
        $workspace->update([
            'status' => $request->boolean('status') ? 'active' : 'inactive',
        ]);

        return response()->json(['success' => true]);
    }
}
