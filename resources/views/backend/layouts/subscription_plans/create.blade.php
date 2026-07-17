@extends('backend.master')

@section('page_title', 'Create Subscription Plan')

@section('content')
    <div class="px-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.plans.store') }}">
                            @csrf

                            <h5 class="mb-4 text-uppercase bg-light-subtle p-2 border rounded text-center">
                                <i class="ti ti-crown fs-lg"></i> Create New Plan
                            </h5>

                            <div class="mb-3">
                                <label class="form-label">Plan Name *</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Billing Cycle *</label>
                                    <select name="billing_cycle" class="form-select" required>
                                        <option value="monthly" {{ old('billing_cycle') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                        <option value="yearly" {{ old('billing_cycle') == 'yearly' ? 'selected' : '' }}>Yearly</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Price (USD) *</label>
                                    <input type="number" step="0.01" min="0" name="price" class="form-control"
                                        value="{{ old('price') }}" required>
                                </div>
                            </div>

                            <p class="text-muted fs-xs mb-2">Leave a limit blank for unlimited.</p>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Max Posts / Day</label>
                                    <input type="number" min="0" name="max_posts_per_day" class="form-control"
                                        value="{{ old('max_posts_per_day') }}">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Max Matches / Day</label>
                                    <input type="number" min="0" name="max_matches_per_day" class="form-control"
                                        value="{{ old('max_matches_per_day') }}">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Max AI Requests / Day</label>
                                    <input type="number" min="0" name="max_ai_requests_per_day" class="form-control"
                                        value="{{ old('max_ai_requests_per_day') }}">
                                </div>
                            </div>

                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                    id="is_active" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-success px-4">Create Plan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
