@extends('backend.master')

@section('page_title', 'User Management')

@push('styles')
    <!-- Datatables css -->
    <link href="{{ asset('backend') }}/assets/plugins/datatables/responsive.bootstrap5.min.css" rel="stylesheet"
        type="text/css" />
@endpush

@section('content')

    {{-- Period Tabs + Stat Cards --}}
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div></div>

        <div class="d-flex align-items-center gap-2">
            <ul class="nav nav-pills bg-light-subtle rounded p-1" id="period-tabs">
                @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $key => $label)
                    <li class="nav-item">
                        <a href="{{ route('admin.user.lists', ['period' => $key]) }}"
                            class="nav-link {{ $period == $key ? 'active' : '' }} py-1 px-2 fs-sm">{{ $label }}</a>
                    </li>
                @endforeach
                <li class="nav-item">
                    <a href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#customDateForm"
                        class="nav-link {{ $period == 'custom' ? 'active' : '' }} py-1 px-2 fs-sm">Custom
                        date</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="collapse {{ $period == 'custom' ? 'show' : '' }}" id="customDateForm">
        <form method="GET" action="{{ route('admin.user.lists') }}" class="d-flex align-items-end gap-2 mb-3">
            <input type="hidden" name="period" value="custom">
            <div>
                <label class="form-label fs-xs mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm"
                    value="{{ request('from') }}">
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
                    <p class="text-muted mb-1">Total users</p>
                    <h3 class="mb-0">{{ number_format($totalUsers) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total active users</p>
                    <h3 class="mb-0">{{ number_format($totalActiveUsers) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total new users</p>
                    <h3 class="mb-0">{{ number_format($totalNewUsers) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-light justify-content-between">
                    <h5 class="mb-0">All users</h5>

                    <div class="d-flex align-items-center gap-2 flex-wrap">

                        <div class="app-search">
                            <input type="text" id="userSearchBox" class="form-control" placeholder="Search here...">
                            <i class="ti ti-search app-search-icon text-muted"></i>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-default dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="ti ti-filter fs-sm me-1"></i> Filter
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 260px;">
                                <div class="mb-2">
                                    <label class="form-label fs-xs">Role</label>
                                    <select data-table-filter="role" class="form-select form-select-sm">
                                        <option value="All">All Roles</option>
                                        <option value="admin">Admin</option>
                                        <option value="provider">Provider</option>
                                        <option value="customer">Customer</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fs-xs">Status</label>
                                    <select data-table-filter="status" class="form-select form-select-sm">
                                        <option value="All">All Statuses</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="banned">Banned</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fs-xs">Gender</label>
                                    <select data-table-filter="gender" class="form-select form-select-sm">
                                        <option value="All">All Genders</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label fs-xs">Plan</label>
                                    <select data-table-filter="plan" class="form-select form-select-sm">
                                        <option value="All">All Plans</option>
                                        <option value="free">Free</option>
                                        @foreach ($plans ?? [] as $plan)
                                            <option value="{{ $plan->slug }}">{{ $plan->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <button type="button" id="bulkDeleteBtn" class="btn btn-outline-danger" disabled>
                            <i class="ti ti-trash fs-sm me-2"></i> Delete
                        </button>

                        <button type="button" id="exportBtn" class="btn btn-default">
                            <i class="ti ti-download fs-sm me-2"></i> Export
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="userTable" data-tables="basic"
                            class="table table-custom dt-responsive align-middle mb-0 table-centered table-select table-hover w-100 mb-0 p-4">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                <tr class="text-uppercase fs-xxs">
                                    <th>
                                        <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                                    </th>
                                    <th>ID</th>
                                    <th>Full name</th>
                                    <th>Email address</th>
                                    <th>Gender</th>
                                    <th>Location</th>
                                    <th>Plan</th>
                                    <th>Verified</th>
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
    <!-- Jquery for Datatables-->
    <script src="{{ asset('backend') }}/assets/plugins/jquery/jquery.min.js"></script>

    <!-- Datatables js -->
    <script src="{{ asset('backend') }}/assets/plugins/datatables/dataTables.min.js"></script>
    <script src="{{ asset('backend') }}/assets/plugins/datatables/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('backend') }}/assets/plugins/datatables/dataTables.responsive.min.js"></script>
    <script src="{{ asset('backend') }}/assets/plugins/datatables/responsive.bootstrap5.min.js"></script>

    <script>
        $(function() {

            let table = $('#userTable').DataTable({

                processing: true,
                serverSide: true,
                responsive: true,
                dom: 'rtip',

                ajax: {
                    url: "{{ route('admin.user.data') }}",
                    data: function(d) {
                        d.role = $('[data-table-filter="role"]').val();
                        d.status = $('[data-table-filter="status"]').val();
                        d.gender = $('[data-table-filter="gender"]').val();
                        d.plan = $('[data-table-filter="plan"]').val();
                        d.search.value = $('#userSearchBox').val();
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
                        data: 'user_id',
                        name: 'id',
                        orderable: false,
                        searchable: false
                    },

                    {
                        data: 'user_info',
                        name: 'name'
                    },

                    {
                        data: 'email',
                        name: 'email'
                    },

                    {
                        data: 'gender_label',
                        name: 'gender',
                        orderable: false,
                        searchable: false
                    },

                    {
                        data: 'location_label',
                        name: 'location',
                        orderable: false,
                        searchable: false
                    },

                    {
                        data: 'plan',
                        name: 'plan',
                        orderable: false,
                        searchable: false
                    },

                    {
                        data: 'verified_badge',
                        name: 'email_verified_at',
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
                    [2, 'asc']
                ],
            });

            // Filters
            $('[data-table-filter]').on('change', function() {
                table.draw();
            });

            // Search box
            let searchTimer;
            $('#userSearchBox').on('keyup', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => table.draw(), 400);
            });

            // Select all
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

            // Bulk delete
            $('#bulkDeleteBtn').on('click', function() {
                let ids = selectedIds();
                if (!ids.length) return;

                Swal.fire({
                    title: 'Delete selected users?',
                    text: `You are about to delete ${ids.length} user(s). This cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: "{{ route('admin.user.bulk-delete') }}",
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

            // Export
            $('#exportBtn').on('click', function() {
                let ids = selectedIds();
                let params = new URLSearchParams({
                    role: $('[data-table-filter="role"]').val(),
                    status: $('[data-table-filter="status"]').val(),
                    gender: $('[data-table-filter="gender"]').val(),
                });
                if (ids.length) {
                    params.set('ids', ids.join(','));
                }
                window.location.href = "{{ route('admin.user.export') }}?" + params.toString();
            });

        });
    </script>
@endpush
