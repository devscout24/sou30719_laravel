@extends('backend.master')

@section('page_title', 'View User')

@section('content')

    <div class="px-3">
        <div class="row">

            {{-- Profile Card --}}
            <div class="col-xl-4 m-auto">

                <div class="card card-top-sticky">
                    <div class="card-body">


                        {{-- Header --}}
                        <div class="d-flex align-items-center mb-4">

                            {{-- Avatar --}}
                            <div class="me-3 position-relative">

                                <img src="{{ asset($user->avatar == 'user.png' ? 'admin.png' : $user->avatar) }}"
                                    class="rounded-circle" width="72" height="72" alt="Avatar">

                            </div>


                            {{-- Name + Role --}}
                            <div>

                                <h5 class="mb-0">
                                    {{ $user->name ?? 'N/A' }}
                                </h5>

                                <span class="badge text-bg-light badge-label">
                                    {{ $user->getRoleNames()->first() ?? 'No Role' }}
                                </span>

                            </div>


                            {{-- Action Menu --}}
                            <div class="ms-auto">

                                <div class="dropdown">

                                    <a href="#" class="btn btn-icon btn-ghost-light text-muted"
                                        data-bs-toggle="dropdown">

                                        <i class="ti ti-dots-vertical fs-xl"></i>

                                    </a>

                                    <ul class="dropdown-menu dropdown-menu-end">

                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.user.edit', $user->id) }}">
                                                Edit User
                                            </a>
                                        </li>

                                        <li>
                                            <a class="dropdown-item text-danger" href="#">
                                                Block User
                                            </a>
                                        </li>

                                    </ul>

                                </div>

                            </div>

                        </div>


                        {{-- User Info --}}
                        <div>


                            {{-- Username --}}
                            @if ($user->username)
                                <div class="d-flex align-items-center gap-2 mb-2">

                                    <div
                                        class="avatar-sm text-bg-light rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="ti ti-user fs-xl"></i>
                                    </div>

                                    <p class="mb-0 fs-sm">
                                        Username: <strong>{{ $user->username }}</strong>
                                    </p>

                                </div>
                            @endif


                            {{-- Email --}}
                            <div class="d-flex align-items-center gap-2 mb-2">

                                <div
                                    class="avatar-sm text-bg-light rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="ti ti-mail fs-xl"></i>
                                </div>

                                <p class="mb-0 fs-sm">

                                    Email:
                                    <a href="mailto:{{ $user->email }}" class="text-primary fw-semibold">
                                        {{ $user->email }}
                                    </a>

                                </p>

                            </div>


                            {{-- Phone --}}
                            @if ($user->phone)
                                <div class="d-flex align-items-center gap-2 mb-2">

                                    <div
                                        class="avatar-sm text-bg-light rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="ti ti-phone fs-xl"></i>
                                    </div>

                                    <p class="mb-0 fs-sm">
                                        Phone: {{ $user->phone }}
                                    </p>

                                </div>
                            @endif


                            {{-- Address --}}
                            @if ($user->address)
                                <div class="d-flex align-items-center gap-2 mb-2">

                                    <div
                                        class="avatar-sm text-bg-light rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="ti ti-map-pin fs-xl"></i>
                                    </div>

                                    <p class="mb-0 fs-sm">
                                        Address: {{ $user->address }}
                                    </p>

                                </div>
                            @endif


                            {{-- Location --}}
                            @if ($user->location)
                                <div class="d-flex align-items-center gap-2 mb-2">

                                    <div
                                        class="avatar-sm text-bg-light rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="ti ti-map fs-xl"></i>
                                    </div>

                                    <p class="mb-0 fs-sm">
                                        Location: {{ $user->location }}
                                    </p>

                                </div>
                            @endif


                            {{-- Status --}}
                            <div class="d-flex align-items-center gap-2 mb-2">

                                <div
                                    class="avatar-sm text-bg-light rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="ti ti-shield-check fs-xl"></i>
                                </div>

                                <p class="mb-0 fs-sm">

                                    Status:

                                    @if ($user->status == 'active')
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    @elseif($user->status == 'inactive')
                                        <span class="badge bg-warning-subtle text-warning">Inactive</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">Banned</span>
                                    @endif

                                </p>

                            </div>


                            {{-- Joined --}}
                            <div class="d-flex align-items-center gap-2 mb-2">

                                <div
                                    class="avatar-sm text-bg-light rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="ti ti-calendar fs-xl"></i>
                                </div>

                                <p class="mb-0 fs-sm">
                                    Joined: {{ $user->created_at->format('d M Y') }}
                                </p>

                            </div>


                        </div>


                    </div>
                </div>

            </div>
        </div>

    @endsection
