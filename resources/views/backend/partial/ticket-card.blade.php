@php
    $ticketStatusMeta = [
        'open'        => ['color' => 'warning', 'label' => 'Pending'],
        'in_progress' => ['color' => 'purple', 'label' => 'On-going'],
        'resolved'    => ['color' => 'success', 'label' => 'Resolved'],
        'closed'      => ['color' => 'secondary', 'label' => 'Closed'],
    ];
    $meta = $ticketStatusMeta[$ticket->status] ?? ['color' => 'secondary', 'label' => ucfirst($ticket->status)];
    $ticketUser = $ticket->user;
    $ticketAvatar = $ticketUser
        ? asset($ticketUser->avatar && $ticketUser->avatar !== 'user.png' ? $ticketUser->avatar : 'admin.png')
        : asset('admin.png');
@endphp

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <div>
                <span class="badge bg-{{ $meta['color'] }}-subtle text-{{ $meta['color'] }} me-2">{{ $meta['label'] }}</span>
                <span class="fw-semibold">Ticket# {{ $ticket->id }}</span>
                <span class="text-muted"> &bull; {{ $ticket->typeLabel() }}</span>
            </div>
            <span class="text-muted fs-xs">
                Posted at {{ optional($ticket->created_at)->format('jS M, Y, h:i A') ?? '—' }}
            </span>
        </div>

        <h6 class="mb-1">{{ $ticket->subject }}</h6>
        <p class="text-muted fs-sm mb-3">{{ \Illuminate\Support\Str::limit($ticket->message, 220) }}</p>

        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <img src="{{ $ticketAvatar }}" class="rounded-circle avatar-xs" alt="avatar">
                <span class="fs-sm">
                    {{ $ticketUser->name ?? 'Unknown' }}
                    @if ($ticketUser?->username)
                        <span class="text-muted">{{ '@' . $ticketUser->username }}</span>
                    @endif
                </span>
            </div>

            <a href="{{ route('admin.support-tickets.show', $ticket->id) }}" class="fw-semibold text-primary fs-sm">
                Open Ticket
            </a>
        </div>
    </div>
</div>
