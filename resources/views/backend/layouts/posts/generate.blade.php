@extends('backend.master')

@section('page_title', 'Generate AI Post')

@section('content')
    <div class="px-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.posts.generate.store') }}">
                            @csrf

                            <h5 class="mb-4 text-uppercase bg-light-subtle p-2 border rounded text-center">
                                <i class="ti ti-sparkles fs-lg"></i> Generate AI Post
                            </h5>

                            <p class="text-muted">
                                Give the AI a theme and it will write and immediately publish a post to the AI-Pal
                                feed for all users.
                            </p>

                            <div class="mb-3">
                                <label class="form-label">Theme *</label>
                                <input type="text" name="theme" class="form-control" value="{{ old('theme') }}"
                                    placeholder="e.g. Tips for staying motivated during winter" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Post Type</label>
                                    <select name="post_type" class="form-select">
                                        <option value="regular" {{ old('post_type') == 'regular' ? 'selected' : '' }}>Regular</option>
                                        <option value="marketplace" {{ old('post_type') == 'marketplace' ? 'selected' : '' }}>Marketplace</option>
                                        <option value="event" {{ old('post_type') == 'event' ? 'selected' : '' }}>Event</option>
                                        <option value="poll" {{ old('post_type') == 'poll' ? 'selected' : '' }}>Poll</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Visibility</label>
                                    <select name="visibility" class="form-select">
                                        <option value="public" {{ old('visibility') == 'public' ? 'selected' : '' }}>Public</option>
                                        <option value="friends" {{ old('visibility') == 'friends' ? 'selected' : '' }}>Friends Only</option>
                                    </select>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-success px-4">
                                    <i class="ti ti-sparkles fs-sm me-1"></i> Generate & Publish
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
