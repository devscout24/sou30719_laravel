@extends('backend.master')

@section('page_title', 'Social Feed Management')

@push('styles')
    <link href="{{ asset('backend') }}/assets/plugins/datatables/responsive.bootstrap5.min.css" rel="stylesheet"
        type="text/css" />
@endpush

@section('content')

    {{-- Period Tabs --}}
    <div class="d-flex align-items-center justify-content-end flex-wrap gap-2 mb-3">
        <ul class="nav nav-pills bg-light-subtle rounded p-1 flex-wrap row-gap-1">
            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $key => $label)
                <li class="nav-item">
                    <a href="{{ route('admin.social-feed.index', ['period' => $key]) }}"
                        class="nav-link {{ $period == $key ? 'active' : '' }} py-1 px-2 fs-sm">{{ $label }}</a>
                </li>
            @endforeach
            <li class="nav-item">
                <a href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#customDateForm"
                    class="nav-link {{ $period == 'custom' ? 'active' : '' }} py-1 px-2 fs-sm">Custom date</a>
            </li>
        </ul>
    </div>

    <div class="collapse {{ $period == 'custom' ? 'show' : '' }}" id="customDateForm">
        <form method="GET" action="{{ route('admin.social-feed.index') }}"
            class="d-flex align-items-end gap-2 mb-3 justify-content-end">
            <input type="hidden" name="period" value="custom">
            <div>
                <label class="form-label fs-xs mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div>
                <label class="form-label fs-xs mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
        </form>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total public posts</p>
                    <h3 class="mb-0">{{ number_format($totalPublicPosts) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total active posts</p>
                    <h3 class="mb-0">{{ number_format($totalActivePosts) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total deactivated posts</p>
                    <h3 class="mb-0">{{ number_format($totalDeactivatedPosts) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-light justify-content-between">
                    <h5 class="mb-0">All Posts</h5>

                    <div class="d-flex align-items-center gap-2 flex-wrap">

                        <div class="app-search">
                            <input type="text" id="postSearchBox" class="form-control" placeholder="Search here...">
                            <i class="ti ti-search app-search-icon text-muted"></i>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-default dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="ti ti-filter fs-sm me-1"></i> Filter
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 220px;">
                                <label class="form-label fs-xs">Status</label>
                                <select data-table-filter="status" class="form-select form-select-sm">
                                    <option value="All">All Statuses</option>
                                    <option value="published">Active</option>
                                    <option value="removed">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <button type="button" id="bulkDeleteBtn" class="btn btn-outline-danger" disabled>
                            <i class="ti ti-trash fs-sm me-2"></i> Delete
                        </button>

                        <button type="button" id="exportBtn" class="btn btn-default">
                            <i class="ti ti-download fs-sm me-2"></i> Export
                        </button>

                        <a href="{{ route('admin.social-feed.index') }}" class="btn btn-default btn-icon"
                            title="Reset">
                            <i class="ti ti-refresh fs-lg"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="postTable" data-tables="basic"
                            class="table table-custom dt-responsive align-middle mb-0 table-centered table-select table-hover w-100 mb-0 p-4">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                <tr class="text-uppercase fs-xxs">
                                    <th>
                                        <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                                    </th>
                                    <th>Post ID</th>
                                    <th>Post</th>
                                    <th>Date/Time Posted</th>
                                    <th>Likes</th>
                                    <th>Shares</th>
                                    <th data-table-sort data-column="status">Status</th>
                                    <th class="text-center" style="width: 1%">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-nowrap">
                                <!-- Yajra Data Here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('backend') }}/assets/plugins/jquery/jquery.min.js"></script>

    <script src="{{ asset('backend') }}/assets/plugins/datatables/dataTables.min.js"></script>
    <script src="{{ asset('backend') }}/assets/plugins/datatables/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('backend') }}/assets/plugins/datatables/dataTables.responsive.min.js"></script>
    <script src="{{ asset('backend') }}/assets/plugins/datatables/responsive.bootstrap5.min.js"></script>

    <script>
        $(function() {

            let table = $('#postTable').DataTable({

                processing: true,
                serverSide: true,
                responsive: true,
                dom: 'rtip',

                ajax: {
                    url: "{{ route('admin.social-feed.data') }}",
                    data: function(d) {
                        d.status = $('[data-table-filter="status"]').val();
                        d.search.value = $('#postSearchBox').val();
                    }
                },

                columns: [
                    {
                        data: 'checkbox',
                        name: 'checkbox',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'post_id',
                        name: 'id',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'post_excerpt',
                        name: 'topic',
                        orderable: false
                    },
                    {
                        data: 'posted',
                        name: 'published_at',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'likes_display',
                        name: 'likes_count',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'shares_display',
                        name: 'shares_count',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status_badge',
                        name: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ],

                order: [
                    [1, 'desc']
                ],
            });

            $('[data-table-filter="status"]').on('change', function() {
                table.draw();
            });

            let searchTimer;
            $('#postSearchBox').on('keyup', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => table.draw(), 400);
            });

            $('#selectAllCheckbox').on('change', function() {
                $('.row-checkbox').prop('checked', $(this).is(':checked'));
                toggleBulkDeleteBtn();
            });

            $(document).on('change', '.row-checkbox', function() {
                toggleBulkDeleteBtn();
            });

            function toggleBulkDeleteBtn() {
                $('#bulkDeleteBtn').prop('disabled', $('.row-checkbox:checked').length === 0);
            }

            function selectedIds() {
                return $('.row-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
            }

            $('#bulkDeleteBtn').on('click', function() {
                let ids = selectedIds();
                if (!ids.length) return;

                Swal.fire({
                    title: 'Delete selected posts?',
                    text: `You are about to delete ${ids.length} post(s). This cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: "{{ route('admin.social-feed.bulk-delete') }}",
                        type: 'POST',
                        data: {
                            ids: ids,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(res) {
                            Swal.fire({
                                title: res.success ? 'Deleted!' : 'Failed',
                                text: res.message,
                                icon: res.success ? 'success' : 'error',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            $('#selectAllCheckbox').prop('checked', false);
                            toggleBulkDeleteBtn();
                            table.draw();
                        },
                        error: function() {
                            Swal.fire('Error', 'Something went wrong.', 'error');
                        }
                    });
                });
            });

            $('#exportBtn').on('click', function() {
                let params = new URLSearchParams({
                    status: $('[data-table-filter="status"]').val(),
                });
                window.location.href = "{{ route('admin.social-feed.export') }}?" + params.toString();
            });

        });
    </script>
@endpush
