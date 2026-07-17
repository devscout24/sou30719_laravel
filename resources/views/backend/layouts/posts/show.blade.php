@extends('backend.master')

@section('page_title', 'Post Detail')

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header justify-content-between">
                    <h5 class="mb-0">{{ $post->topic ?? $post->title ?? '(untitled)' }}</h5>
                    <span class="badge bg-{{ $post->created_by === 'ai' ? 'info' : 'primary' }}-subtle text-{{ $post->created_by === 'ai' ? 'info' : 'primary' }}">
                        {{ strtoupper($post->created_by) }}
                    </span>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Author:</strong> {{ $post->user->name ?? 'Unknown' }}</p>
                    <p class="mb-1"><strong>Workspace:</strong> {{ $post->workspace->title ?? '—' }}</p>
                    <p class="mb-1"><strong>Type:</strong> {{ ucfirst($post->type) }}</p>
                    <p class="mb-1"><strong>Visibility:</strong> {{ ucfirst($post->visibility) }}</p>
                    <p class="mb-3"><strong>Status:</strong> {{ ucfirst($post->status) }}</p>
                    <hr>
                    <p style="white-space: pre-wrap;">{{ $post->content }}</p>

                    @if ($post->tags)
                        <div class="mt-3">
                            @foreach ($post->tags as $tag)
                                <span class="badge bg-light text-dark badge-label me-1">#{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($post->images->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @foreach ($post->images as $image)
                                <img src="{{ $image->full_url }}" width="120" class="rounded border">
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Moderation</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.posts.destroy', $post) }}" method="POST" class="delete-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="ti ti-trash fs-sm me-1"></i> Delete Post
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.posts.index') }}" class="btn btn-default">
        <i class="ti ti-arrow-left fs-sm me-1"></i> Back to Posts
    </a>
@endsection
