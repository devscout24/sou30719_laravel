@extends('backend.master')

@section('page_title', 'Help Center')

@section('content')

    {{-- Period Tabs --}}
    <div class="d-flex align-items-center justify-content-end flex-wrap gap-2 mb-3">
        <ul class="nav nav-pills bg-light-subtle rounded p-1 flex-wrap row-gap-1">
            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $key => $label)
                <li class="nav-item">
                    <a href="{{ route('admin.support-tickets.index', array_merge(request()->except(['period', 'from', 'to', 'page']), ['period' => $key])) }}"
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
        <form method="GET" action="{{ route('admin.support-tickets.index') }}"
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
                    <p class="text-muted mb-1">Total ticket</p>
                    <h3 class="mb-0">{{ number_format($totalTickets) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total pending ticket</p>
                    <h3 class="mb-0">{{ number_format($pendingTickets) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total on-going ticket</p>
                    <h3 class="mb-0">{{ number_format($ongoingTickets) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total resolved ticket</p>
                    <h3 class="mb-0">{{ number_format($resolvedTickets) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total new ticket</p>
                    <h3 class="mb-0">{{ number_format($newTickets) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.support-tickets.index') }}" id="ticketFilterForm">
        <input type="hidden" name="period" value="{{ $period }}">
        @if ($period == 'custom')
            <input type="hidden" name="from" value="{{ request('from') }}">
            <input type="hidden" name="to" value="{{ request('to') }}">
        @endif

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h5 class="mb-0">All Tickets</h5>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="app-search">
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                        placeholder="Search here...">
                    <i class="ti ti-search app-search-icon text-muted"></i>
                </div>

                <div class="dropdown">
                    <button class="btn btn-default dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="ti ti-filter fs-sm me-1"></i> Filter
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 260px;">
                        <div class="mb-2">
                            <label class="form-label fs-xs">By type</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="All">All Types</option>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" {{ request('type') == $value ? 'selected' : '' }}>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fs-xs">By status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="All">All Statuses</option>
                                <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Pending</option>
                                <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>
                                    On-going</option>
                                <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>
                                    Resolved</option>
                                <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed
                                </option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Apply Filter</button>
                    </div>
                </div>

                <a href="{{ route('admin.support-tickets.export', request()->query()) }}" class="btn btn-default">
                    <i class="ti ti-download fs-sm me-2"></i> Export
                </a>

                <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-default btn-icon" title="Reset">
                    <i class="ti ti-refresh fs-lg"></i>
                </a>
            </div>
        </div>

        {{-- Quick status pills --}}
        <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
            @php
                $statusPills = [
                    'All' => 'All tickets',
                    'open' => 'Pending tickets',
                    'in_progress' => 'On-going tickets',
                    'resolved' => 'Resolved tickets',
                ];
                $currentStatus = request('status', 'All');
            @endphp
            @foreach ($statusPills as $value => $label)
                <a href="{{ route('admin.support-tickets.index', array_merge(request()->except(['status', 'page']), ['status' => $value])) }}"
                    class="btn btn-sm {{ $currentStatus == $value ? 'btn-primary' : 'btn-light' }} rounded-pill px-3">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </form>

    @forelse ($tickets as $ticket)
        @include('backend.partial.ticket-card', ['ticket' => $ticket])
    @empty
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                No tickets found.
            </div>
        </div>
    @endforelse

    @if ($tickets->hasPages())
        <div class="d-flex justify-content-center">
            {{ $tickets->onEachSide(1)->links() }}
        </div>
    @endif

@endsection
