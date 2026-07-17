@extends('backend.master')

@section('page_title', 'Workspaces')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-light justify-content-between">
                    <h5 class="mb-0">All Workspaces</h5>

                    <a href="{{ route('admin.workspaces.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus fs-sm me-2"></i> Add Workspace
                    </a>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-centered table-select table-hover w-100 mb-0">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                <tr class="text-uppercase fs-xxs">
                                    <th>Title</th>
                                    <th>Prompt Pill</th>
                                    <th>Slug</th>
                                    <th>Nav Access</th>
                                    <th>Posts</th>
                                    <th>Sort</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width: 1%">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-nowrap">
                                @forelse ($workspaces as $workspace)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">{{ $workspace->title }}</span>
                                            @unless ($workspace->is_supported)
                                                <span class="badge bg-secondary-subtle text-secondary badge-label ms-1">No handler</span>
                                            @endunless
                                        </td>
                                        <td>{{ $workspace->prompt }}</td>
                                        <td><code>{{ $workspace->slug }}</code></td>
                                        <td>
                                            @forelse ($workspace->navPermissions as $perm)
                                                <span class="badge bg-info-subtle text-info badge-label me-1">{{ $perm->nav_key }}</span>
                                            @empty
                                                <span class="text-muted fs-xs">None</span>
                                            @endforelse
                                        </td>
                                        <td>{{ $workspace->posts_count }}</td>
                                        <td>{{ $workspace->sort_order }}</td>
                                        <td>
                                            <div class="form-check form-switch d-inline-block ms-2">
                                                <input class="form-check-input status-toggle" type="checkbox"
                                                    data-id="{{ $workspace->id }}" {{ $workspace->isActive() ? 'checked' : '' }}>
                                            </div>
                                            <span
                                                class="badge bg-{{ $workspace->isActive() ? 'success-subtle text-success' : 'warning-subtle text-warning' }} badge-label">{{ ucfirst($workspace->status) }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <a href="{{ route('admin.workspaces.edit', $workspace) }}"
                                                    class="btn btn-default btn-icon btn-sm">
                                                    <i class="ti ti-edit fs-lg"></i>
                                                </a>

                                                <form action="{{ route('admin.workspaces.destroy', $workspace) }}"
                                                    method="POST" class="d-inline delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-default btn-icon btn-sm text-danger">
                                                        <i class="ti ti-trash fs-lg"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">No workspaces yet.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $('.status-toggle').on('change', function() {
            let id = $(this).data('id');
            let checked = $(this).is(':checked');

            $.ajax({
                url: `/workspaces/${id}/status`,
                type: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                data: JSON.stringify({
                    status: checked
                }),
                success: function(data) {
                    if (!data.success) {
                        alert('Status update failed!');
                        location.reload();
                    } else {
                        Swal.fire({
                            title: 'Status Updated Successfully!',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false,
                            willClose: () => location.reload()
                        })
                    }
                },
                error: function() {
                    alert('Something went wrong!');
                    location.reload();
                }
            });
        });
    </script>
@endpush
