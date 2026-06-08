@extends('backend.master')

@section('page_title', 'User Management')

@push('styles')
    <!-- Datatables css -->
    <link href="{{ asset('backend') }}/assets/plugins/datatables/responsive.bootstrap5.min.css" rel="stylesheet"
        type="text/css" />
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-light justify-content-between">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="me-2 fw-semibold">Filter By:</span>

                        <!-- Task Status Filter -->
                        <div class="app-search">
                            <select data-table-filter="status" class="form-select form-control my-1 my-md-0">
                                <option value="All">By Role</option>
                                <option value="admin">Admin</option>
                                <option value="provider">Provider</option>
                                <option value="customer">Customer</option>
                            </select>
                            <i class="ti ti-list-check app-search-icon text-muted"></i>
                        </div>

                        <!-- Priority Filter -->
                        <div class="app-search">
                            <select data-table-filter="priority" class="form-select form-control my-1 my-md-0">
                                <option value="All">By Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="banned">Banned</option>
                            </select>
                            <i class="ti ti-list app-search-icon text-muted"></i>
                        </div>

                    </div>

                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.user.create') }}" class="btn btn-primary ms-1"> <i
                                class="ti ti-user-plus fs-sm me-2"></i>
                            Add User </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="userTable" data-tables="basic"
                            class="table table-custom dt-responsive align-middle mb-0 table-centered table-select table-hover w-100 mb-0 p-4">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                <tr class="text-uppercase fs-xxs">
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined Date</th>
                                    <th data-table-sort data-column="status">Status</th>
                                    <th class="text-center" style="width: 1%">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-nowrap">
                                <!-- Yajra Data Here -->
                            </tbody>
                        </table>
                    </div>
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
@endsection

@push('scripts')
    <!-- Jquery for Datatables-->
    <script src="{{ asset('backend') }}/assets/plugins/jquery/jquery.min.js"></script>

    <!-- Datatables js -->
    <script src="{{ asset('backend') }}/assets/plugins/datatables/dataTables.min.js"></script>
    <script src="{{ asset('backend') }}/assets/plugins/datatables/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('backend') }}/assets/plugins/datatables/dataTables.responsive.min.js"></script>
    <script src="{{ asset('backend') }}/assets/plugins/datatables/responsive.bootstrap5.min.js"></script>

    <!-- Page js -->
    <script src="assets/js/pages/datatables-basic.js"></script>

    <script>
        $(function() {

            let table = $('#userTable').DataTable({

                processing: true,
                serverSide: true,
                responsive: true,

                ajax: {
                    url: "{{ route('admin.user.data') }}",
                    data: function(d) {

                        d.role = $('[data-table-filter="status"]').val();
                        d.status = $('[data-table-filter="priority"]').val();
                    }
                },

                columns: [

                    {
                        data: null,
                        name: 'index',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },


                    {
                        data: 'name',
                        name: 'name'
                    },

                    {
                        data: 'email',
                        name: 'email'
                    },

                    {
                        data: 'role',
                        name: 'role',
                        render: function(data) {

                            return `
                        <span class="text-uppercase fw-medium badge bg-success-subtle text-info">
                            ${data}
                        </span>
                    `;
                        }
                    },

                    {
                        data: 'joined',
                        name: 'joined'
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
                    [0, 'desc']
                ],
            });


            // Role Filter
            $('[data-table-filter="status"]').on('change', function() {
                table.draw();
            });


            // Status Filter
            $('[data-table-filter="priority"]').on('change', function() {
                table.draw();
            });

        });
    </script>
@endpush
