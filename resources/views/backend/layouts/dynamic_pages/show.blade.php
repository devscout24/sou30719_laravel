@extends('backend.master')

@section('page_title', 'Dynamic Pages - ' . $page->page_name)

@section('content')
<div class="card">

    <div class="card-header">
        <h5>{{ $page->page_name }}</h5>
    </div>

    <div class="card-body">

        <div class="mb-3">
            <strong>Status:</strong>
            <span class="badge bg-{{ $page->is_active ? 'success' : 'warning' }}">
                {{ $page->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <div class="mb-3">
            <strong>Last Updated:</strong>
            {{ $page->updated_at->format('d M Y, h:i A') }}
        </div>

        <hr>

        <div class="content-area">
            {!! $page->content !!}
        </div>

    </div>

    <div class="card-footer text-end">
        <a href="{{ route('dynamic.pages.edit', $page->id) }}" class="btn btn-primary">
            Edit Page
        </a>
    </div>

</div>
@endsection
