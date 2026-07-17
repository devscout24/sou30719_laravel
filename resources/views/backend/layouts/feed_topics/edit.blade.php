@extends('backend.master')

@section('page_title', 'Edit Feed Topic')

@section('content')
    <div class="px-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.feed-topics.update', $topic) }}">
                            @csrf
                            @method('PUT')

                            <h5 class="mb-4 text-uppercase bg-light-subtle p-2 border rounded text-center">
                                <i class="ti ti-edit fs-lg"></i> Edit Feed Topic
                            </h5>

                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $topic->name) }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Icon</label>
                                <input type="text" name="icon" class="form-control"
                                    value="{{ old('icon', $topic->icon) }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Keywords</label>
                                <small class="text-muted d-block mb-1">Comma-separated keywords used to auto-match posts to this topic.</small>
                                <input type="text" name="tag_keywords" class="form-control"
                                    value="{{ old('tag_keywords', implode(', ', $topic->tag_keywords ?? [])) }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" min="0" class="form-control"
                                    value="{{ old('sort_order', $topic->sort_order) }}">
                            </div>

                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                    id="is_active" {{ old('is_active', $topic->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-success px-4">Update Topic</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
