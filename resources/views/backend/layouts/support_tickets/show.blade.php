@extends('backend.master')

@section('page_title', 'Ticket details')

@php
    $statusOptions = [
        'open'        => 'Pending',
        'in_progress' => 'On-going',
        'resolved'    => 'Resolved',
        'closed'      => 'Closed',
    ];
    $statusColors = [
        'open'        => 'warning',
        'in_progress' => 'purple',
        'resolved'    => 'success',
        'closed'      => 'secondary',
    ];
    $ticketUser = $ticket->user;
    $ticketAvatar = $ticketUser
        ? asset($ticketUser->avatar && $ticketUser->avatar !== 'user.png' ? $ticketUser->avatar : 'admin.png')
        : asset('admin.png');
@endphp

@section('content')

    <a href="{{ route('admin.support-tickets.index') }}" class="d-inline-flex align-items-center gap-1 text-muted mb-3">
        <i class="ti ti-arrow-left fs-lg"></i> Back to Help Center
    </a>

    <div class="row">
        {{-- Ticket description --}}
        <div class="col-xl-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-2">{{ $ticket->subject }}</h5>
                    <p class="text-muted mb-0" style="white-space: pre-wrap;">{{ $ticket->message }}</p>

                    @if ($ticket->full_attachment_url)
                        <div class="mt-3">
                            <img src="{{ $ticket->full_attachment_url }}" class="img-fluid rounded border"
                                alt="Attachment">
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Status + Info --}}
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Status</h5>
                </div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <span class="text-muted">Ticket status</span>

                    <div class="dropdown">
                        <button class="btn btn-{{ $statusColors[$ticket->status] ?? 'secondary' }}-subtle text-{{ $statusColors[$ticket->status] ?? 'secondary' }} dropdown-toggle"
                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ $statusOptions[$ticket->status] ?? ucfirst($ticket->status) }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @foreach ($statusOptions as $value => $label)
                                <li>
                                    <form action="{{ route('admin.support-tickets.status', $ticket) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="{{ $value }}">
                                        <button type="submit"
                                            class="dropdown-item {{ $ticket->status == $value ? 'active' : '' }}">
                                            {{ $label }}
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ticket info</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fs-sm">Requester</span>
                        <div class="d-flex align-items-center gap-2">
                            <img src="{{ $ticketAvatar }}" class="rounded-circle avatar-xs" alt="avatar">
                            @if ($ticketUser)
                                <a href="{{ route('admin.user.show', $ticketUser->id) }}" class="fw-medium">
                                    {{ $ticketUser->name }}
                                    @if ($ticketUser->username)
                                        <span class="text-muted">{{ '@' . $ticketUser->username }}</span>
                                    @endif
                                </a>
                            @else
                                <span class="fw-medium">Unknown</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fs-sm">Ticket ID</span>
                        <span class="fw-medium">#{{ $ticket->id }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fs-sm">Date posted</span>
                        <span
                            class="fw-medium">{{ optional($ticket->created_at)->format('d M Y, h:i A') ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fs-sm">Ticket category</span>
                        <span class="fw-medium">{{ $ticket->typeLabel() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-sm">Status</span>
                        <span
                            class="badge bg-{{ $statusColors[$ticket->status] ?? 'secondary' }}-subtle text-{{ $statusColors[$ticket->status] ?? 'secondary' }}">
                            {{ $statusOptions[$ticket->status] ?? ucfirst($ticket->status) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Conversation --}}
    <div class="card">
        <div class="card-body">

            <form action="{{ route('admin.support-tickets.replies.store', $ticket) }}" method="POST" class="mb-4">
                @csrf
                <textarea name="message" rows="3" class="form-control mb-2" placeholder="Type response here" required></textarea>
                <div class="text-end">
                    <button type="submit" class="btn btn-dark">Submit</button>
                </div>
            </form>

            <div class="d-flex flex-column gap-3">

                {{-- Opening message --}}
                <div>
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="fw-semibold d-block">{{ $ticketUser->name ?? 'Requester' }}</span>
                            @if ($ticketUser)
                                <a href="{{ route('admin.user.show', $ticketUser->id) }}"
                                    class="fs-xs text-primary">View Profile</a>
                            @endif
                        </div>
                        <span class="text-muted fs-xs">
                            {{ optional($ticket->created_at)->diffForHumans() ?? '—' }}
                            <i class="ti ti-check fs-xs"></i>
                        </span>
                    </div>
                    <div class="bg-light-subtle rounded p-3 mt-1">{{ $ticket->message }}</div>
                </div>

                @foreach ($ticket->replies as $reply)
                    @php $replyUser = $reply->user; @endphp
                    <div>
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="fw-semibold d-block">
                                    {{ $replyUser->name ?? ($reply->is_admin ? 'Admin' : 'User') }}
                                    @if ($reply->is_admin)
                                        <span class="text-muted fs-xs fw-normal">Admin</span>
                                    @endif
                                </span>
                            </div>
                            <span class="text-muted fs-xs">
                                {{ $reply->created_at->diffForHumans() }}
                                <i class="ti ti-check fs-xs"></i>
                            </span>
                        </div>
                        <div
                            class="rounded p-3 mt-1 {{ $reply->is_admin ? 'bg-dark text-white' : 'bg-light-subtle' }}">
                            {{ $reply->message }}
                        </div>
                    </div>
                @endforeach

            </div>
        </div>
    </div>

    {{-- Recent tickets of this user --}}
    <form method="GET" action="{{ route('admin.support-tickets.show', $ticket) }}">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h5 class="mb-0">Recent tickets of this user</h5>

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
                        <label class="form-label fs-xs">By status</label>
                        <select name="recent_status" class="form-select form-select-sm mb-2">
                            <option value="All">All Statuses</option>
                            <option value="open" {{ request('recent_status') == 'open' ? 'selected' : '' }}>Pending
                            </option>
                            <option value="in_progress" {{ request('recent_status') == 'in_progress' ? 'selected' : '' }}>
                                On-going</option>
                            <option value="resolved" {{ request('recent_status') == 'resolved' ? 'selected' : '' }}>
                                Resolved</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Apply Filter</button>
                    </div>
                </div>

                <a href="{{ route('admin.support-tickets.export', ['search' => request('recent_search')]) }}"
                    class="btn btn-default">
                    <i class="ti ti-download fs-sm me-2"></i> Export
                </a>

                <a href="{{ route('admin.support-tickets.show', $ticket) }}" class="btn btn-default btn-icon"
                    title="Reset">
                    <i class="ti ti-refresh fs-lg"></i>
                </a>
            </div>
        </div>
    </form>

    @forelse ($recentTickets as $recentTicket)
        @include('backend.partial.ticket-card', ['ticket' => $recentTicket])
    @empty
        <div class="card">
            <div class="card-body text-center text-muted py-4">
                No other tickets from this user.
            </div>
        </div>
    @endforelse

    @if ($recentTickets->hasPages())
        <div class="d-flex justify-content-center">
            {{ $recentTickets->onEachSide(1)->links() }}
        </div>
    @endif

@endsection
