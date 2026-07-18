@extends('backend.master')

@section('page_title', 'LLM Agent Log')

@section('content')

    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
        <i class="ti ti-tools fs-lg"></i>
        <div>
            <strong>Coming soon.</strong> This page previews a planned AI-agent monitoring layer. There's no
            agent-orchestration or execution-logging system in the backend yet, so everything below is a static
            preview — no numbers here are live.
        </div>
    </div>

    {{-- Period Tabs (preview only) --}}
    <div class="d-flex align-items-center justify-content-end flex-wrap gap-2 mb-3">
        <ul class="nav nav-pills bg-light-subtle rounded p-1 flex-wrap row-gap-1 preview-tabs">
            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $key => $label)
                <li class="nav-item">
                    <a href="javascript:void(0)"
                        class="nav-link {{ $period == $key ? 'active' : '' }} py-1 px-2 fs-sm">{{ $label }}</a>
                </li>
            @endforeach
            <li class="nav-item">
                <a href="javascript:void(0)" class="nav-link py-1 px-2 fs-sm">Custom date</a>
            </li>
        </ul>
    </div>

    <ul class="nav nav-tabs nav-bordered mb-3 preview-tabs">
        @foreach (['All', 'Social', 'Marketplace', 'Interest Hub', 'Courier', 'Events'] as $i => $label)
            <li class="nav-item">
                <a href="javascript:void(0)" class="nav-link {{ $i === 0 ? 'active' : '' }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total agent execution</p>
                    <h3 class="mb-0 text-muted">—</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Active agents</p>
                    <h3 class="mb-0 text-muted">—</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Average success rate</p>
                    <h3 class="mb-0 text-muted">—</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total token consumption</p>
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
                        <h5 class="mb-0">System Module Trace Logs</h5>
                        <span class="text-muted fs-sm">Real-time execution streams from distributed modules</span>
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
                                    <th>Module</th>
                                    <th>Agent name</th>
                                    <th>Intent</th>
                                    <th>Status</th>
                                    <th>Execution time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="ti ti-robot-off fs-2 d-block mb-2"></i>
                                        No agent execution data yet — this feature is coming soon.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-1">Module Health Metrics</h5>
                    <p class="text-muted fs-sm mb-3">Predictive failure analysis across all module nodes based on
                        current latency spikes.</p>

                    @foreach (['Interests Hub Stability', 'Courier Latency Index', 'Match Success Deviation'] as $metric)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between fs-xs text-uppercase text-muted mb-1">
                                <span>{{ $metric }}</span>
                                <span>—</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-secondary" style="width: 0%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-primary text-white h-100">
                <div class="card-body d-flex flex-column">
                    <div
                        class="avatar-sm bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center mb-3">
                        <i class="ti ti-sparkles fs-lg"></i>
                    </div>
                    <h5 class="text-white">Optimize Agent Execution</h5>
                    <p class="fs-sm mb-3 text-white-50">
                        This optimization suggestion will use real usage data once agent execution logging is
                        wired up.
                    </p>
                    <button type="button" class="btn btn-light mt-auto preview-action">
                        Enable Optimization
                    </button>
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
