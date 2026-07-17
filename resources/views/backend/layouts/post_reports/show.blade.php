@extends('backend.master')

@section('page_title', 'Post Report #' . $report->id)

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Reported Content</h5>
                </div>
                <div class="card-body">
                    @if ($report->post)
                        <p class="mb-1"><strong>Author:</strong> {{ $report->post->user->name ?? 'Unknown' }}</p>
                        <p class="mb-1"><strong>Topic:</strong> {{ $report->post->topic ?? '—' }}</p>
                        <p class="mb-3"><strong>Status:</strong> {{ ucfirst($report->post->status) }}</p>
                        <hr>
                        <p style="white-space: pre-wrap;">{{ $report->post->content }}</p>
                    @else
                        <div class="text-muted">This post has already been removed.</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Report Details</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Reported by:</strong> {{ $report->user->name ?? 'Unknown' }}
                        ({{ $report->user->email ?? '—' }})</p>
                    <p class="mb-1"><strong>Reason:</strong> {{ ucfirst(str_replace('_', ' ', $report->reason)) }}</p>
                    <p class="mb-1"><strong>Reported on:</strong> {{ $report->created_at->format('d M Y, h:i A') }}</p>

                    @if ($report->description)
                        <p class="mb-0"><strong>Additional details:</strong> {{ $report->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Moderation Actions</h5>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <span class="badge bg-{{ $report->status == 'pending' ? 'danger' : ($report->status == 'reviewed' ? 'success' : 'secondary') }}-subtle text-{{ $report->status == 'pending' ? 'danger' : ($report->status == 'reviewed' ? 'success' : 'secondary') }} mb-2">
                        {{ ucfirst($report->status) }}
                    </span>

                    <form action="{{ route('admin.post-reports.reviewed', $report) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="btn btn-success w-100">
                            <i class="ti ti-check fs-sm me-1"></i> Mark as Reviewed
                        </button>
                    </form>

                    <form action="{{ route('admin.post-reports.dismissed', $report) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="btn btn-secondary w-100">
                            <i class="ti ti-x fs-sm me-1"></i> Dismiss Report
                        </button>
                    </form>

                    @if ($report->post)
                        <form action="{{ route('admin.post-reports.delete-post', $report) }}" method="POST"
                            class="delete-form">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="ti ti-trash fs-sm me-1"></i> Remove Reported Post
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.post-reports.index') }}" class="btn btn-default">
        <i class="ti ti-arrow-left fs-sm me-1"></i> Back to Reports
    </a>
@endsection
