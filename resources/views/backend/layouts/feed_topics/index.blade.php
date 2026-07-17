@extends('backend.master')

@section('page_title', 'Feed Topics')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-light justify-content-between">
                    <div>
                        <h5 class="mb-0">Fixed Feed Topics</h5>
                        <small class="text-muted">Built-in topics shown to every user, alongside their own custom
                            topics.</small>
                    </div>

                    <a href="{{ route('admin.feed-topics.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus fs-sm me-2"></i> Add Topic
                    </a>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-centered table-select table-hover w-100 mb-0">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                <tr class="text-uppercase fs-xxs">
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Icon</th>
                                    <th>Keywords</th>
                                    <th>Sort</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width: 1%">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-nowrap">
                                @forelse ($topics as $topic)
                                    <tr>
                                        <td><span class="fw-semibold">{{ $topic->name }}</span></td>
                                        <td><code>{{ $topic->slug }}</code></td>
                                        <td>{{ $topic->icon ?? '—' }}</td>
                                        <td class="text-wrap">
                                            @forelse ($topic->tag_keywords ?? [] as $keyword)
                                                <span class="badge bg-light text-dark badge-label me-1">{{ $keyword }}</span>
                                            @empty
                                                <span class="text-muted fs-xs">—</span>
                                            @endforelse
                                        </td>
                                        <td>{{ $topic->sort_order }}</td>
                                        <td>
                                            <div class="form-check form-switch d-inline-block ms-2">
                                                <input class="form-check-input status-toggle" type="checkbox"
                                                    data-id="{{ $topic->id }}" {{ $topic->is_active ? 'checked' : '' }}>
                                            </div>
                                            <span
                                                class="badge bg-{{ $topic->is_active ? 'success-subtle text-success' : 'warning-subtle text-warning' }} badge-label">{{ $topic->is_active ? 'Active' : 'Inactive' }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <a href="{{ route('admin.feed-topics.edit', $topic) }}"
                                                    class="btn btn-default btn-icon btn-sm">
                                                    <i class="ti ti-edit fs-lg"></i>
                                                </a>

                                                <form action="{{ route('admin.feed-topics.destroy', $topic) }}"
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
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">No fixed feed topics yet.</div>
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
                url: `/feed-topics/${id}/status`,
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
