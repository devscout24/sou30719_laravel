@extends('backend.master')

@section('page_title', 'Dynamic Pages - ' . $page->page_name)

@section('content')
    <div class="card">

        <div class="card-header">
            <h5>Edit Page</h5>
        </div>

        <form action="{{ route('dynamic.pages.update', $page->id) }}" method="POST">

            @csrf
            @method('PUT')

            <div class="card-body">

                <div class="mb-3">
                    <label>Page Name</label>
                    <input type="text" name="page_name" class="form-control"
                        value="{{ old('page_name', $page->page_name) }}">
                </div>

                <div class="mb-3">
                    <label>Content</label>
                    <textarea name="content" class="form-control ckeditor" rows="8">{{ old('content', $page->content) }}</textarea>
                </div>

            </div>

            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success">
                    Update Page
                </button>
            </div>

        </form>

    </div>
@endsection

@push('scripts')
    @include('backend.partial.ck_editor')
@endpush
