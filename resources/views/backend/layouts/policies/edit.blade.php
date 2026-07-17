@extends('backend.master')

@section('page_title', 'Disclaimers Policy')

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Edit Disclaimers</h5>
            <small class="text-muted">Shown to users in-app under Policies & Disclaimers.</small>
        </div>

        <form action="{{ route('admin.policies.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card-body">
                <div class="mb-3">
                    <label>Content</label>
                    <textarea name="content" class="form-control ckeditor" rows="10">{{ old('content', $policy->content) }}</textarea>
                </div>
            </div>

            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success">Update Disclaimers</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    @include('backend.partial.ck_editor')
@endpush
