@extends('backend.master')

@section('page_title', 'Create Workspace')

@section('content')
    <div class="px-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.workspaces.store') }}">
                            @csrf

                            <h5 class="mb-4 text-uppercase bg-light-subtle p-2 border rounded text-center">
                                <i class="ti ti-apps fs-lg"></i> Create New Workspace
                            </h5>

                            <div class="mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Prompt Pill *</label>
                                <small class="text-muted d-block mb-1">The pill text matched against incoming chat messages.</small>
                                <input type="text" name="prompt" class="form-control" value="{{ old('prompt') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Slug *</label>
                                <small class="text-muted d-block mb-1">Internal key selecting the backend handler (letters, numbers, dashes, underscores).</small>
                                <input type="text" name="slug" class="form-control" value="{{ old('slug') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Navbar Access</label>
                                <small class="text-muted d-block mb-1">Which app sections this workspace can be used from.</small>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach ($navSections as $section)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="nav_keys[]"
                                                value="{{ $section }}" id="nav_{{ $section }}"
                                                {{ collect(old('nav_keys', []))->contains($section) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="nav_{{ $section }}">{{ $section }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" min="0" class="form-control" value="{{ old('sort_order', 0) }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>

                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_supported" value="1"
                                    id="is_supported" {{ old('is_supported') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_supported">
                                    Backend handler already implemented for this workspace
                                </label>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-success px-4">Create Workspace</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
