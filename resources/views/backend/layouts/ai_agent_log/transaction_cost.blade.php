@extends('backend.master')

@section('page_title', 'LLM Transaction & Cost')

@section('content')

    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
        <i class="ti ti-tools fs-lg"></i>
        <div>
            <strong>Coming soon.</strong> This page previews a planned per-model LLM usage & cost ledger. The app
            currently calls a single OpenAI model with no token/cost logging, so everything below is a static
            preview — no numbers here are live.
        </div>
    </div>

    <p class="text-muted mb-3">Real-time inference tracking and infrastructure efficiency metrics.</p>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-1">LLM Cost for Current Month</h5>
                    <span class="text-muted fs-xs text-uppercase d-block mb-2">LLM Models</span>

                    @foreach (['GPT 4.0', 'GPT Mini', 'GPT Nano', 'Claude'] as $model)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span>{{ $model }}</span>
                            <span class="text-muted">—</span>
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-between align-items-center bg-light-subtle rounded p-2 mt-2">
                        <span class="fw-semibold">Total</span>
                        <span class="fw-semibold text-muted">—</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-1">LLM Cost Year To Date</h5>
                    <span class="text-muted fs-xs text-uppercase d-block mb-2">LLM Models</span>

                    @foreach (['GPT 4.0', 'GPT Mini', 'GPT Nano', 'Claude'] as $model)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span>{{ $model }}</span>
                            <span class="text-muted">—</span>
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-between align-items-center bg-light-subtle rounded p-2 mt-2">
                        <span class="fw-semibold">Total</span>
                        <span class="fw-semibold text-muted">—</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs nav-bordered mb-3 preview-tabs">
        @foreach (['GPT-4o', 'Claude 3.5 Sonnet', 'Gemini 1.5 Pro', 'Llama 3 (8B)'] as $i => $label)
            <li class="nav-item">
                <a href="javascript:void(0)" class="nav-link {{ $i === 0 ? 'active' : '' }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total token (24h)</p>
                    <h3 class="mb-0 text-muted">—</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total cost (USD)</p>
                    <h3 class="mb-0 text-muted">—</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Average latency</p>
                    <h3 class="mb-0 text-muted">—</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Error rate</p>
                    <h3 class="mb-0 text-muted">—</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-light justify-content-between">
                    <div>
                        <h5 class="mb-0">Transaction Log</h5>
                        <span class="text-muted fs-sm">Detailed record of every API invocation</span>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="app-search">
                            <input type="text" class="form-control" placeholder="Search here..." disabled>
                            <i class="ti ti-search app-search-icon text-muted"></i>
                        </div>
                        <button type="button" class="btn btn-default preview-action">
                            <i class="ti ti-download fs-sm me-2"></i> Export
                        </button>
                        <button type="button" class="btn btn-default btn-icon preview-action">
                            <i class="ti ti-refresh fs-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-custom table-centered w-100 mb-0">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                <tr class="text-uppercase fs-xxs">
                                    <th><input type="checkbox" class="form-check-input" disabled></th>
                                    <th>Timestamp</th>
                                    <th>Model</th>
                                    <th>Prompt tokens</th>
                                    <th>Output tokens</th>
                                    <th>Total cost (USD)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="ti ti-robot-off fs-2 d-block mb-2"></i>
                                        No API invocation logs yet — this feature is coming soon.
                                    </td>
                                </tr>
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
        $(document).on('click', '.preview-action, .preview-tabs .nav-link', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Coming soon',
                text: 'This feature isn\'t wired up to live data yet.',
                icon: 'info',
                timer: 1800,
                showConfirmButton: false
            });
        });
    </script>
@endpush
