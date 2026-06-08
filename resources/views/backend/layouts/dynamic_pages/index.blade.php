@extends('backend.master')

@section('page_title', 'Dynamic Pages')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-centered table-select table-hover w-100 mb-0">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                <tr class="text-uppercase fs-xxs">
                                    <th>Page Name</th>
                                    <th data-table-sort data-column="status">Status</th>
                                    <th data-table-sort>Last Updated At</th>
                                    <th class="text-center" style="width: 1%">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-nowrap">
                                @forelse ($dynamicPages as $page)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold link-reset">{{ $page->page_name }}</span>
                                        </td>
                                        <td>
                                            <div class="form-check form-switch d-inline-block ms-2">
                                                <input class="form-check-input status-toggle" type="checkbox"
                                                    data-id="{{ $page->id }}" {{ $page->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label"
                                                    for="statusSwitch{{ $page->id }}"></label>
                                            </div>
                                            <span
                                                class="badge bg-{{ $page->is_active ? 'success-subtle text-success' : 'warning-subtle text-warning' }} badge-label">{{ $page->is_active ? 'Active' : 'Inactive' }}</span>
                                        </td>
                                        <td>
                                            <span
                                                class="fw-medium badge bg-success-subtle text-success badge-label">{{ $page->updated_at->diffForHumans() }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">

                                                {{-- View --}}
                                                <a href="{{ route('dynamic.pages.show', $page->id) }}"
                                                    class="btn btn-default btn-icon btn-sm">
                                                    <i class="ti ti-eye fs-lg"></i>
                                                </a>

                                                {{-- Edit --}}
                                                <a href="{{ route('dynamic.pages.edit', $page->id) }}"
                                                    class="btn btn-default btn-icon btn-sm">
                                                    <i class="ti ti-edit fs-lg"></i>
                                                </a>

                                            </div>
                                        </td>

                                    </tr>
                                @empty
                                    <tr></tr>
                                    <td colspan="4" class="text-center py-4">
                                        <div class="text-muted">
                                            No dynamic pages available.
                                        </div>
                                    </td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div data-table-pagination-info="Support Tickets"></div>
                        <div data-table-pagination></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $('.status-toggle').on('change', function() {

            let pageId = $(this).data('id');
            let status = $(this).is(':checked') ? 1 : 0;

            $.ajax({
                url: `/dynamic-pages/${pageId}/status`,
                type: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                data: JSON.stringify({
                    status: status
                }),
                success: function(data) {
                    if (!data.success) {
                        alert('Status update failed!');
                        location.reload();
                    }else{
                        // Sweet Alert Loading Animation
                        Swal.fire({
                            title: 'Status Updated Successfully!',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false,
                            willClose: () => {
                                location.reload();
                            }
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
