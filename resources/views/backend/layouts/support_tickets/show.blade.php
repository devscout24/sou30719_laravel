@extends('backend.master')

@section('page_title', 'Support Ticket #' . $ticket->id)

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ $ticket->subject }}</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>From:</strong> {{ $ticket->user->name ?? 'Unknown' }}
                        ({{ $ticket->user->email ?? '—' }})</p>
                    <p class="mb-3"><strong>Submitted:</strong> {{ $ticket->created_at->format('d M Y, h:i A') }}</p>
                    <hr>
                    <p style="white-space: pre-wrap;">{{ $ticket->message }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ticket Status</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.support-tickets.status', $ticket) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <select name="status" class="form-select">
                                <option value="open" {{ $ticket->status == 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ $ticket->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="resolved" {{ $ticket->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="closed" {{ $ticket->status == 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success w-100">Update Status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-default">
        <i class="ti ti-arrow-left fs-sm me-1"></i> Back to Tickets
    </a>
@endsection
