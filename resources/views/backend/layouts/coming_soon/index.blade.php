@extends('backend.master')

@section('page_title', $feature)

@section('content')

    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
        <i class="ti ti-tools fs-lg"></i>
        <div>
            <strong>{{ $feature }} — Coming soon.</strong> This section isn't available yet.
        </div>
    </div>

@endsection
