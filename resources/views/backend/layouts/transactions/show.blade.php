@extends('backend.master')

@section('page_title', 'Transaction details')

@php
    $txStatusColors = [
        'paid'     => 'success',
        'pending'  => 'warning',
        'failed'   => 'danger',
        'refunded' => 'secondary',
    ];
    $txColor = $txStatusColors[$transaction->status] ?? 'secondary';
    $txUser = $transaction->user;
    $txAvatar = $txUser
        ? asset($txUser->avatar && $txUser->avatar !== 'user.png' ? $txUser->avatar : 'admin.png')
        : asset('admin.png');
@endphp

@section('content')

    <a href="{{ route('admin.transactions.index') }}" class="d-inline-flex align-items-center gap-1 text-muted mb-3">
        <i class="ti ti-arrow-left fs-lg"></i> Back to Transactions
    </a>

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Transaction ID #{{ $transaction->id }}</h5>

                    <span class="text-muted fs-sm d-block">Amount</span>
                    <h3 class="mb-3">${{ number_format($transaction->amount, 0) }}</h3>

                    <span class="text-muted fs-sm d-block">Tax</span>
                    <h5 class="mb-3">${{ number_format($transaction->tax, 0) }}</h5>

                    <a href="{{ route('admin.transactions.invoice', $transaction->id) }}" class="btn btn-dark">
                        Invoice
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Transaction info</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fs-sm">Date</span>
                        <span
                            class="fw-medium">{{ optional($transaction->created_at)->format('d M Y, h:i A') ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fs-sm">User</span>
                        <div class="d-flex align-items-center gap-2">
                            <img src="{{ $txAvatar }}" class="rounded-circle avatar-xs" alt="avatar">
                            @if ($txUser)
                                <a href="{{ route('admin.user.show', $txUser->id) }}" class="fw-medium">
                                    {{ $txUser->name }}
                                    @if ($txUser->username)
                                        <span class="text-muted">{{ '@' . $txUser->username }}</span>
                                    @endif
                                </a>
                            @else
                                <span class="fw-medium">Unknown</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fs-sm">Plan</span>
                        <span class="fw-medium">{{ optional(optional($transaction->subscription)->plan)->name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fs-sm">Payment method</span>
                        <span class="fw-medium">{{ $transaction->methodLabel() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-sm">Status</span>
                        <span class="badge bg-{{ $txColor }}-subtle text-{{ $txColor }}">
                            {{ $transaction->statusLabel() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Transactions from this user --}}
    <form method="GET" action="{{ route('admin.transactions.show', $transaction) }}">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h5 class="mb-0">Transactions from this user</h5>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="app-search">
                    <input type="text" name="recent_search" value="{{ request('recent_search') }}"
                        class="form-control" placeholder="Search here...">
                    <i class="ti ti-search app-search-icon text-muted"></i>
                </div>

                <div class="dropdown">
                    <button class="btn btn-default dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="ti ti-filter fs-sm me-1"></i> Filter
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 220px;">
                        <label class="form-label fs-xs">By module</label>
                        <select name="recent_context" class="form-select form-select-sm mb-2">
                            <option value="All">All Modules</option>
                            @foreach (\App\Models\Payment::CONTEXTS as $value => $label)
                                <option value="{{ $value }}"
                                    {{ request('recent_context') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Apply Filter</button>
                    </div>
                </div>

                <a href="{{ route('admin.transactions.export', ['search' => request('recent_search')]) }}"
                    class="btn btn-default">
                    <i class="ti ti-download fs-sm me-2"></i> Export
                </a>

                <a href="{{ route('admin.transactions.show', $transaction) }}" class="btn btn-default btn-icon"
                    title="Reset">
                    <i class="ti ti-refresh fs-lg"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-custom table-centered table-select table-hover w-100 mb-0">
                    <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                        <tr class="text-uppercase fs-xxs">
                            <th><input type="checkbox" class="form-check-input"></th>
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
                        @forelse ($recentTransactions as $recentTransaction)
                            @include('backend.partial.transaction-row', ['transaction' => $recentTransaction])
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No other transactions from this
                                    user.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($recentTransactions->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $recentTransactions->onEachSide(1)->links() }}
        </div>
    @endif

@endsection
