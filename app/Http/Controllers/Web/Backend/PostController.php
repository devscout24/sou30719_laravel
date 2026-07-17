<?php

namespace App\Http\Controllers\Web\Backend;

use App\Exceptions\AIServiceException;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AI\PostCuratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;

class PostController extends Controller
{
    public function data(Request $request)
    {
        $query = Post::query()->with(['user', 'workspace']);

        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        if ($request->created_by && $request->created_by != 'All') {
            $query->where('created_by', $request->created_by);
        }

        return DataTables::of($query)
            ->addIndexColumn()

            ->addColumn('author', function ($post) {
                return $post->user->name ?? 'Unknown';
            })

            ->addColumn('topic_display', function ($post) {
                return $post->topic ?: $post->title ?: '(untitled)';
            })

            ->addColumn('created_by_badge', function ($post) {
                $color = $post->created_by === 'ai' ? 'info' : 'primary';

                return '<span class="badge bg-' . $color . '-subtle text-' . $color . '">' . strtoupper($post->created_by) . '</span>';
            })

            ->addColumn('published', function ($post) {
                return $post->published_at ? $post->published_at->format('d M Y') : '—';
            })

            ->addColumn('status_badge', function ($post) {
                $map = [
                    'published' => 'success',
                    'draft'     => 'warning',
                    'archived'  => 'secondary',
                ];
                $color = $map[$post->status] ?? 'secondary';

                return '<span class="badge bg-' . $color . '-subtle text-' . $color . '">' . ucfirst($post->status) . '</span>';
            })

            ->addColumn('action', function ($post) {
                return '
                <div class="d-flex justify-content-center gap-1">
                    <a href="' . route('admin.posts.show', $post->id) . '" class="btn btn-default btn-icon btn-sm">
                        <i class="ti ti-eye fs-lg"></i>
                    </a>
                </div>
            ';
            })

            ->rawColumns(['created_by_badge', 'status_badge', 'action'])
            ->make(true);
    }

    public function index()
    {
        return view('backend.layouts.posts.index');
    }

    public function show(Post $post)
    {
        $post->load(['user', 'workspace', 'images']);

        return view('backend.layouts.posts.show', compact('post'));
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()->route('admin.posts.index')->with('success', 'Post deleted successfully');
    }

    public function generateForm()
    {
        return view('backend.layouts.posts.generate');
    }

    public function generate(Request $request, PostCuratorService $curator)
    {
        $validation = Validator::make($request->all(), [
            'theme'      => 'required|string|max:255',
            'post_type'  => 'nullable|in:regular,marketplace,event,poll',
            'visibility' => 'nullable|in:public,friends',
        ]);

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first())->withInput();
        }

        try {
            $result = $curator->generateAdminPost($request->theme);
        } catch (AIServiceException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $post = Post::create([
            'user_id'           => Auth::id(),
            'topic'             => $result['topic'],
            'type'              => $request->post_type ?? 'regular',
            'created_by'        => 'ai',
            'content'           => $result['description'],
            'short_description' => $result['short_description'],
            'tags'              => $result['tags'],
            'visibility'        => $request->visibility ?? 'public',
            'status'            => 'published',
            'published_at'      => now(),
        ]);

        return redirect()->route('admin.posts.show', $post)->with('success', 'AI post generated and published successfully');
    }
}
