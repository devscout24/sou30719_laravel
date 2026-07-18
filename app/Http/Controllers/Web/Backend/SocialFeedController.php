<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\DataTables;

class SocialFeedController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'daily');

        [$from, $to] = $this->resolvePeriodRange($period, $request->get('from'), $request->get('to'));

        $base = Post::query()->userCreated()->regular();

        $totalPublicPosts      = (clone $base)->where('visibility', 'public')->count();
        $totalActivePosts      = (clone $base)->where('status', 'published')->count();
        $totalDeactivatedPosts = (clone $base)->where('status', 'removed')->count();
        $newPosts              = (clone $base)->whereBetween('created_at', [$from, $to])->count();

        return view('backend.layouts.social_feed.index', compact(
            'period',
            'totalPublicPosts',
            'totalActivePosts',
            'totalDeactivatedPosts',
            'newPosts'
        ));
    }

    public function data(Request $request)
    {
        $query = Post::query()->userCreated()->regular()->with(['user', 'images'])->withCount(['likes', 'shares']);

        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)

            ->addIndexColumn()

            ->addColumn('checkbox', function ($post) {
                return '<input type="checkbox" class="form-check-input row-checkbox" value="' . $post->id . '">';
            })

            ->addColumn('post_id', function ($post) {
                return '#' . $post->id;
            })

            ->addColumn('post_excerpt', function ($post) {
                $thumb = $post->images->first();
                $thumbUrl = $thumb ? $thumb->full_url : asset('admin.png');
                $text = e(\Illuminate\Support\Str::limit($post->displayTitle(), 60));

                return '
                <div class="d-flex align-items-center gap-2">
                    <img src="' . $thumbUrl . '" class="rounded" width="42" height="42" style="object-fit: cover;" alt="post">
                    <span>' . $text . '</span>
                </div>
            ';
            })

            ->addColumn('posted', function ($post) {
                $date = $post->published_at ?? $post->created_at;

                return $date ? $date->format('d M Y, g:ia') : '—';
            })

            ->addColumn('likes_display', function ($post) {
                return $this->humanCount($post->likes_count);
            })

            ->addColumn('shares_display', function ($post) {
                return $this->humanCount($post->shares_count);
            })

            ->addColumn('status_badge', function ($post) {
                if ($post->status === 'published') {
                    return '<span class="badge bg-primary-subtle text-primary">Active</span>';
                }

                if ($post->status === 'removed') {
                    return '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>';
                }

                return '<span class="badge bg-warning-subtle text-warning">' . ucfirst($post->status) . '</span>';
            })

            ->addColumn('action', function ($post) {
                return '
                <div class="dropdown">
                    <a href="#" class="btn btn-default btn-icon btn-sm" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ti ti-dots-vertical fs-lg"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="' . route('admin.social-feed.show', $post->id) . '">
                                <i class="ti ti-eye fs-sm me-1 align-middle"></i> View
                            </a>
                        </li>
                        <li>
                            <form action="' . route('admin.social-feed.status', $post->id) . '" method="POST">
                                ' . csrf_field() . '
                                <input type="hidden" name="status" value="' . ($post->status === 'published' ? 'removed' : 'published') . '">
                                <button type="submit" class="dropdown-item">
                                    <i class="ti ti-' . ($post->status === 'published' ? 'eye-off' : 'eye') . ' fs-sm me-1 align-middle"></i>
                                    ' . ($post->status === 'published' ? 'Deactivate' : 'Activate') . '
                                </button>
                            </form>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="' . route('admin.social-feed.destroy', $post->id) . '" method="POST" class="delete-form">
                                ' . csrf_field() . method_field('DELETE') . '
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="ti ti-trash fs-sm me-1 align-middle"></i> Delete
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            ';
            })

            ->rawColumns(['checkbox', 'post_excerpt', 'status_badge', 'action'])

            ->make(true);
    }

    private function humanCount(int $count): string
    {
        if ($count >= 1000) {
            return round($count / 1000, 1) . 'k';
        }

        return (string) $count;
    }

    private function resolvePeriodRange(string $period, $from = null, $to = null): array
    {
        $now = Carbon::now();

        return match ($period) {
            'weekly'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'yearly'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom'  => [
                $from ? Carbon::parse($from)->startOfDay() : $now->copy()->startOfMonth(),
                $to ? Carbon::parse($to)->endOfDay() : $now->copy()->endOfDay(),
            ],
            default   => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    public function show(Post $post)
    {
        $post->load([
            'user',
            'workspace',
            'images',
            'likes.user' => function ($q) {
                $q->latest();
            },
            'shares.user' => function ($q) {
                $q->latest();
            },
        ]);

        return view('backend.layouts.social_feed.show', compact('post'));
    }

    public function updateStatus(Request $request, Post $post)
    {
        $request->validate([
            'status' => 'required|in:published,removed',
        ]);

        $post->update(['status' => $request->status]);

        $message = $request->status === 'removed' ? 'Post deactivated successfully.' : 'Post activated successfully.';

        return redirect()->back()->with('success', $message);
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()->route('admin.social-feed.index')->with('success', 'Post deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->map(fn($id) => (int) $id);

        if ($ids->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No posts selected.']);
        }

        Post::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Selected posts deleted successfully.']);
    }

    public function export(Request $request)
    {
        $query = Post::query()->userCreated()->regular()->with('user')->withCount(['likes', 'shares']);

        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        $filename = 'social-feed-posts-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Author', 'Title', 'Posted', 'Likes', 'Shares', 'Status']);

            $query->orderBy('id')->chunk(200, function ($posts) use ($handle) {
                foreach ($posts as $post) {
                    fputcsv($handle, [
                        $post->id,
                        $post->user->name ?? 'Unknown',
                        $post->displayTitle(),
                        optional($post->published_at ?? $post->created_at)->format('Y-m-d H:i'),
                        $post->likes_count,
                        $post->shares_count,
                        ucfirst($post->status),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
