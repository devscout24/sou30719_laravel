@extends('backend.master')

@section('page_title', 'Post #' . $post->id)

@php
    $postUser = $post->user;
    $postAvatar = $postUser
        ? asset($postUser->avatar && $postUser->avatar !== 'user.png' ? $postUser->avatar : 'admin.png')
        : asset('admin.png');
    $imageCount = $post->images->count();
@endphp

@section('content')

    <a href="{{ route('admin.social-feed.index') }}" class="d-inline-flex align-items-center gap-1 text-muted mb-3">
        <i class="ti ti-arrow-left fs-lg"></i> Post #{{ $post->id }}
    </a>

    <div class="row">
        <div class="col-lg-5">
            @if ($imageCount)
                <div id="postCarousel" class="carousel slide position-relative rounded overflow-hidden bg-light"
                    data-bs-ride="false">
                    <span class="badge bg-dark bg-opacity-75 position-absolute top-0 end-0 m-2"
                        style="z-index: 2;" id="carouselCounter">1/{{ $imageCount }}</span>

                    <div class="carousel-inner">
                        @foreach ($post->images as $image)
                            <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                                <img src="{{ $image->full_url }}" class="d-block w-100"
                                    style="height: 340px; object-fit: cover;" alt="Post image">
                            </div>
                        @endforeach
                    </div>

                    @if ($imageCount > 1)
                        <button class="carousel-control-prev" type="button" data-bs-target="#postCarousel"
                            data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#postCarousel"
                            data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </button>

                        <div class="carousel-indicators" style="position: relative; margin-top: 8px;">
                            @foreach ($post->images as $image)
                                <button type="button" data-bs-target="#postCarousel" data-bs-slide-to="{{ $loop->index }}"
                                    class="{{ $loop->first ? 'active' : '' }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded bg-light d-flex align-items-center justify-content-center"
                    style="height: 340px;">
                    <i class="ti ti-photo-off fs-2 text-muted"></i>
                </div>
            @endif
        </div>

        <div class="col-lg-7">
            <span class="text-muted fs-xs text-uppercase">Title</span>
            <h5 class="mb-3">{{ $post->displayTitle() }}</h5>

            <span class="text-muted fs-xs text-uppercase">Description</span>
            <p class="mb-0" style="white-space: pre-wrap;">{{ $post->content }}</p>

            @if (!empty($post->tags))
                <div class="d-flex flex-wrap gap-1 mt-3">
                    @foreach ($post->tags as $tag)
                        <span class="badge bg-light text-body border">#{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <span class="text-muted fs-xs text-uppercase d-block mb-3">Post specific details</span>

                    <span class="text-muted fs-xs text-uppercase d-block mb-2">Created by</span>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="{{ $postAvatar }}" class="rounded-circle" width="40" height="40" alt="avatar">
                        <div>
                            @if ($postUser)
                                <a href="{{ route('admin.user.show', $postUser->id) }}"
                                    class="fw-semibold d-block text-decoration-underline">{{ $postUser->name }}</a>
                                @if ($postUser->username)
                                    <span class="text-muted fs-sm">{{ '@' . $postUser->username }}</span>
                                @endif
                            @else
                                <span class="fw-semibold d-block">Unknown</span>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fs-sm">Created on</span>
                        <span class="fw-medium">{{ optional($post->created_at)->format('d M, Y, h:i A') ?? '—' }}</span>
                    </div>

                    <form action="{{ route('admin.social-feed.status', $post->id) }}" method="POST"
                        class="post-status-form">
                        @csrf
                        @if ($post->status === 'removed')
                            <input type="hidden" name="status" value="published">
                            <button type="submit" class="btn btn-success-subtle text-success"
                                data-confirm-title="Activate post?"
                                data-confirm-text="This will make the post visible to users again.">
                                Activate post
                            </button>
                        @else
                            <input type="hidden" name="status" value="removed">
                            <button type="submit" class="btn btn-danger-subtle text-danger"
                                data-confirm-title="Deactivate post?"
                                data-confirm-text="This will hide the post from the social feed.">
                                Deactivate post
                            </button>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <span class="text-muted fs-xs text-uppercase d-block mb-2">Published date &amp; time</span>
                    <p class="fw-medium mb-3">
                        {{ optional($post->published_at ?? $post->created_at)->format('d M, y h:i A') ?? '—' }}
                    </p>

                    <span class="text-muted fs-xs text-uppercase d-block mb-2">Posted in</span>
                    <p class="fw-medium mb-3">{{ $post->workspace->title ?? 'Social feed' }}</p>

                    <div class="row">
                        <div class="col-6">
                            <span class="text-muted fs-xs text-uppercase d-block mb-2">Likes</span>
                            <div class="dropdown">
                                <a href="#" class="fw-medium text-body dropdown-toggle" data-bs-toggle="dropdown">
                                    {{ number_format($post->likes->count()) }}
                                </a>
                                <ul class="dropdown-menu">
                                    @forelse ($post->likes->take(10) as $like)
                                        <li><span class="dropdown-item-text fs-sm">{{ $like->user->name ?? 'Unknown' }}</span></li>
                                    @empty
                                        <li><span class="dropdown-item-text fs-sm text-muted">No likes yet</span></li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted fs-xs text-uppercase d-block mb-2">Shares</span>
                            <div class="dropdown">
                                <a href="#" class="fw-medium text-body dropdown-toggle" data-bs-toggle="dropdown">
                                    {{ number_format($post->shares->count()) }}
                                </a>
                                <ul class="dropdown-menu">
                                    @forelse ($post->shares->take(10) as $share)
                                        <li><span class="dropdown-item-text fs-sm">{{ $share->user->name ?? 'Unknown' }}</span></li>
                                    @empty
                                        <li><span class="dropdown-item-text fs-sm text-muted">No shares yet</span></li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        $(document).on('submit', '.post-status-form', function(e) {
            e.preventDefault();
            let form = this;

            Swal.fire({
                title: $(form).find('button[type=submit]').data('confirm-title'),
                text: $(form).find('button[type=submit]').data('confirm-text'),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        document.getElementById('postCarousel')?.addEventListener('slid.bs.carousel', function(e) {
            document.getElementById('carouselCounter').textContent = (e.to + 1) + '/{{ $imageCount }}';
        });
    </script>
@endpush
