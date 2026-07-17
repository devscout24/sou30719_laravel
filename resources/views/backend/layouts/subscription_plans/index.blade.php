@extends('backend.master')

@section('page_title', 'Subscription Plans')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-light justify-content-between">
                    <h5 class="mb-0">All Subscription Plans</h5>

                    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus fs-sm me-2"></i> Add Plan
                    </a>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-centered table-select table-hover w-100 mb-0">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                <tr class="text-uppercase fs-xxs">
                                    <th>Name</th>
                                    <th>Billing Cycle</th>
                                    <th>Price</th>
                                    <th>Post / Match / AI Limits (per day)</th>
                                    <th>Subscribers</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width: 1%">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-nowrap">
                                @forelse ($plans as $plan)
                                    <tr>
                                        <td><span class="fw-semibold">{{ $plan->name }}</span></td>
                                        <td>{{ ucfirst($plan->billing_cycle) }}</td>
                                        <td>${{ number_format($plan->price, 2) }}</td>
                                        <td>
                                            {{ $plan->max_posts_per_day ?? '∞' }} /
                                            {{ $plan->max_matches_per_day ?? '∞' }} /
                                            {{ $plan->max_ai_requests_per_day ?? '∞' }}
                                        </td>
                                        <td>{{ $plan->user_subscriptions_count }}</td>
                                        <td>
                                            <div class="form-check form-switch d-inline-block ms-2">
                                                <input class="form-check-input status-toggle" type="checkbox"
                                                    data-id="{{ $plan->id }}" {{ $plan->is_active ? 'checked' : '' }}>
                                            </div>
                                            <span
                                                class="badge bg-{{ $plan->is_active ? 'success-subtle text-success' : 'warning-subtle text-warning' }} badge-label">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <a href="{{ route('admin.plans.edit', $plan) }}"
                                                    class="btn btn-default btn-icon btn-sm">
                                                    <i class="ti ti-edit fs-lg"></i>
                                                </a>

                                                <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST"
                                                    class="d-inline delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-default btn-icon btn-sm text-danger">
                                                        <i class="ti ti-trash fs-lg"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">No subscription plans yet.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $('.status-toggle').on('change', function() {
            let id = $(this).data('id');
            let checked = $(this).is(':checked');

            $.ajax({
                url: `/subscription-plans/${id}/status`,
                type: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                data: JSON.stringify({
                    status: checked
                }),
                success: function(data) {
                    if (!data.success) {
                        alert('Status update failed!');
                        location.reload();
                    } else {
                        Swal.fire({
                            title: 'Status Updated Successfully!',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false,
                            willClose: () => location.reload()
                        })
                    }
                },
                error: function() {
                    alert('Something went wrong!');
                    location.reload();
                }
            });
        });
    </script>
@endpush
