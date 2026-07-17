@extends('backend.master')

@section('page_title', 'Help & Support Messages')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">User Support Messages</h5>
                    <small class="text-muted">Contact form / help requests submitted from the app.</small>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-centered table-select table-hover w-100 mb-0">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                <tr class="text-uppercase fs-xxs">
                                    <th>Customer</th>
                                    <th>Subject</th>
                                    <th>Submitted</th>
                                    <th class="text-center" style="width: 1%">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-nowrap">
                                @forelse ($messages as $message)
                                    <tr>
                                        <td>{{ $message->customer->name ?? 'Unknown' }}</td>
                                        <td>{{ $message->subject }}</td>
                                        <td>{{ $message->created_at->format('d M Y') }}</td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <a href="{{ route('admin.help-support.show', $message) }}"
                                                    class="btn btn-default btn-icon btn-sm">
                                                    <i class="ti ti-eye fs-lg"></i>
                                                </a>

                                                <form action="{{ route('admin.help-support.destroy', $message) }}"
                                                    method="POST" class="d-inline delete-form">
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
                                        <td colspan="4" class="text-center py-4">
                                            <div class="text-muted">No messages yet.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer border-0">
                    {{ $messages->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection
