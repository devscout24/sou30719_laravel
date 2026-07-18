@extends('backend.master')

@section('page_title', 'Transactions')

@section('content')

    {{-- Period Tabs --}}
    <div class="d-flex align-items-center justify-content-end flex-wrap gap-2 mb-3">
        <ul class="nav nav-pills bg-light-subtle rounded p-1 flex-wrap row-gap-1">
            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $key => $label)
                <li class="nav-item">
                    <a href="{{ route('admin.transactions.index', array_merge(request()->except(['period', 'from', 'to', 'page']), ['period' => $key])) }}"
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
        <form method="GET" action="{{ route('admin.transactions.index') }}"
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
        <div class="col-md">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total transactions</p>
                    <h3 class="mb-0">{{ number_format($totalTransactions) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total transactions /$</p>
                    <h3 class="mb-0">${{ number_format($totalTransactionsSum, 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total pending transactions</p>
                    <h3 class="mb-0">{{ number_format($pendingTransactions) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total failed transactions</p>
                    <h3 class="mb-0">{{ number_format($failedTransactions) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total complete trans.</p>
                    <h3 class="mb-0">{{ number_format($completeTransactions) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.transactions.index') }}" id="txFilterForm">
        <input type="hidden" name="period" value="{{ $period }}">
        @if ($period == 'custom')
            <input type="hidden" name="from" value="{{ request('from') }}">
            <input type="hidden" name="to" value="{{ request('to') }}">
        @endif

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h5 class="mb-0">All transactions</h5>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="app-search">
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                        placeholder="Search here...">
                    <i class="ti ti-search app-search-icon text-muted"></i>
                </div>

                <div class="dropdown">
                    <button class="btn btn-default dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="ti ti-filter fs-sm me-1"></i> Filter
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 240px;">
                        <label class="form-label fs-xs">By status</label>
                        <select name="status" class="form-select form-select-sm mb-2">
                            <option value="All">All Statuses</option>
                            <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Successful
                            </option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending
                            </option>
                            <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed
                            </option>
                            <option value="refunded" {{ request('status') == 'refunded' ? 'selected' : '' }}>Refunded
                            </option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Apply Filter</button>
                    </div>
                </div>

                <a href="{{ route('admin.transactions.export', request()->query()) }}" id="exportBtn"
                    class="btn btn-default">
                    <i class="ti ti-download fs-sm me-2"></i> Export
                </a>

                <a href="{{ route('admin.transactions.index') }}" class="btn btn-default btn-icon" title="Reset">
                    <i class="ti ti-refresh fs-lg"></i>
                </a>
            </div>
        </div>

        {{-- Module pills --}}
        <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
            @php
                $currentContext = request('context', 'All');
            @endphp
            <a href="{{ route('admin.transactions.index', array_merge(request()->except(['context', 'page']), ['context' => 'All'])) }}"
                class="btn btn-sm {{ $currentContext == 'All' ? 'btn-primary' : 'btn-light' }} rounded-pill px-3">
                All
            </a>
            @foreach ($contexts as $value => $label)
                <a href="{{ route('admin.transactions.index', array_merge(request()->except(['context', 'page']), ['context' => $value])) }}"
                    class="btn btn-sm {{ $currentContext == $value ? 'btn-primary' : 'btn-light' }} rounded-pill px-3">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-custom table-centered table-select table-hover w-100 mb-0">
                    <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                        <tr class="text-uppercase fs-xxs">
                            <th><input type="checkbox" id="selectAllCheckbox" class="form-check-input"></th>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Username</th>
                            <th>Mode</th>
                            <th>Amount</th>
                            <th>Tax</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-nowrap">
                        @forelse ($transactions as $transaction)
                            @include('backend.partial.transaction-row', ['transaction' => $transaction])
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">No transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($transactions->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $transactions->onEachSide(1)->links() }}
        </div>
    @endif

@endsection

@push('scripts')
    <script>
        $(function() {
            $('#selectAllCheckbox').on('change', function() {
                $('.row-checkbox').prop('checked', $(this).is(':checked'));
            });

            $('#exportBtn').on('click', function(e) {
                let ids = $('.row-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (ids.length) {
                    e.preventDefault();
                    let url = new URL(this.href);
                    url.searchParams.set('ids', ids.join(','));
                    window.location.href = url.toString();
                }
            });
        });
    </script>
@endpush
