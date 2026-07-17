@extends('backend.master')

@section('page_title', 'User Subscriptions')

@push('styles')
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

                        <div class="app-search">
                            <select data-table-filter="status" class="form-select form-control my-1 my-md-0">
                                <option value="All">By Status</option>
                                <option value="active">Active</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="expired">Expired</option>
                            </select>
                            <i class="ti ti-list app-search-icon text-muted"></i>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="subTable" data-tables="basic"
                            class="table table-custom dt-responsive align-middle mb-0 table-centered table-select table-hover w-100 mb-0 p-4">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                <tr class="text-uppercase fs-xxs">
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Plan</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th data-table-sort data-column="status">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-nowrap"></tbody>
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
            let table = $('#subTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,

                ajax: {
                    url: "{{ route('admin.billing.subscriptions.data') }}",
                    data: function(d) {
                        d.status = $('[data-table-filter="status"]').val();
                    }
                },

                columns: [{
                        data: null,
                        name: 'index',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    {
                        data: 'user_name',
                        name: 'user.name'
                    },
                    {
                        data: 'plan_name',
                        name: 'plan.name'
                    },
                    {
                        data: 'start',
                        name: 'start_date'
                    },
                    {
                        data: 'end',
                        name: 'end_date'
                    },
                    {
                        data: 'status_badge',
                        name: 'status'
                    }
                ],

                order: [
                    [0, 'desc']
                ],
            });

            $('[data-table-filter="status"]').on('change', function() {
                table.draw();
            });
        });
    </script>
@endpush
