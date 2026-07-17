@extends('backend.master')

@section('page_title', 'Support Message')

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">{{ $message->subject }}</h5>
        </div>
        <div class="card-body">
            <p class="mb-1"><strong>From:</strong> {{ $message->customer->name ?? 'Unknown' }}
                ({{ $message->customer->email ?? '—' }})</p>
            <p class="mb-3"><strong>Submitted:</strong> {{ $message->created_at->format('d M Y, h:i A') }}</p>
            <hr>
            <p style="white-space: pre-wrap;">{{ $message->message }}</p>
        </div>
    </div>

    <a href="{{ route('admin.help-support.index') }}" class="btn btn-default">
        <i class="ti ti-arrow-left fs-sm me-1"></i> Back to Messages
    </a>
@endsection
