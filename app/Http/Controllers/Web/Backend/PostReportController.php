<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\PostReport;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class PostReportController extends Controller
{
    public function data(Request $request)
    {
        $query = PostReport::query()->with(['post', 'user']);

        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addIndexColumn()

            ->addColumn('reporter', function ($report) {
                return $report->user->name ?? 'Unknown';
            })

            ->addColumn('post_excerpt', function ($report) {
                $post = $report->post;

                if (!$post) {
                    return '<span class="text-muted">Post deleted</span>';
                }

                return e($post->topic ?: $post->title ?: $post->short_description ?: '(no content)');
            })

            ->addColumn('reason', function ($report) {
                return ucfirst(str_replace('_', ' ', $report->reason));
            })

            ->addColumn('created', function ($report) {
                return $report->created_at->format('d M Y');
            })

            ->addColumn('status_badge', function ($report) {
                $map = [
                    'pending'   => 'danger',
                    'reviewed'  => 'success',
                    'dismissed' => 'secondary',
                ];
                $color = $map[$report->status] ?? 'secondary';

                return '<span class="badge bg-' . $color . '-subtle text-' . $color . '">' . ucfirst($report->status) . '</span>';
            })

            ->addColumn('action', function ($report) {
                return '
                <div class="d-flex justify-content-center gap-1">
                    <a href="' . route('admin.post-reports.show', $report->id) . '" class="btn btn-default btn-icon btn-sm">
                        <i class="ti ti-eye fs-lg"></i>
                    </a>
                </div>
            ';
            })

            ->rawColumns(['post_excerpt', 'status_badge', 'action'])
            ->make(true);
    }

    public function index()
    {
        return view('backend.layouts.post_reports.index');
    }

    public function show(PostReport $postReport)
    {
        $postReport->load(['post.user', 'user']);

        return view('backend.layouts.post_reports.show', ['report' => $postReport]);
    }

    public function markReviewed(PostReport $postReport)
    {
        $postReport->update(['status' => 'reviewed']);

        return redirect()->route('admin.post-reports.show', $postReport)->with('success', 'Report marked as reviewed');
    }

    public function markDismissed(PostReport $postReport)
    {
        $postReport->update(['status' => 'dismissed']);

        return redirect()->route('admin.post-reports.show', $postReport)->with('success', 'Report dismissed');
    }

    public function deletePost(PostReport $postReport)
    {
        if ($postReport->post) {
            $postReport->post->delete();
        }

        $postReport->update(['status' => 'reviewed']);

        return redirect()->route('admin.post-reports.show', $postReport)->with('success', 'Reported post removed and report marked as reviewed');
    }
}
