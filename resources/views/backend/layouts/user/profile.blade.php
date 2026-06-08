@extends('backend.master')

@section('page_title' , 'My Profile')

@section('content')
    <div class="px-3">
        <div class="row">
            <div class="col-xl-4 m-auto">
                <div class="card card-top-sticky">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <div class="me-3 position-relative">
                                <img src="{{ asset(Auth::user()->avatar == 'user.png' ? 'admin.png' : Auth::user()->avatar) }}" alt="avatar" class="rounded-circle"
                                    width="72" height="72" />
                            </div>
                            <div>
                                <h5 class="mb-0 d-flex align-items-center">
                                    <a href="#!" class="link-reset">{{ Auth::user()->name }}</a>
                                </h5>
                                <span class="badge text-bg-light badge-label">{{ Auth::user()->getRoleNames()->first(); }}</span>
                            </div>
                            <div class="ms-auto">
                                <div class="dropdown">
                                    <a href="#" class="btn btn-icon btn-ghost-light text-muted"
                                        data-bs-toggle="dropdown">
                                        <i class="ti ti-dots-vertical fs-xl"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.user.profile.edit', ['type' => 'profile']) }}">Edit Profile</a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="{{ route('admin.user.profile.edit', ['type' => 'email']) }}">Change Email</a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="{{ route('admin.user.profile.edit', ['type' => 'password']) }}">Change Password</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div
                                    class="avatar-sm text-bg-light bg-opacity-75 d-flex align-items-center justify-content-center rounded-circle">
                                    <i class="ti ti-briefcase fs-xl"></i>
                                </div>
                                <p class="mb-0 fs-sm">Owner of the Sunfix App</p>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div
                                    class="avatar-sm text-bg-light bg-opacity-75 d-flex align-items-center justify-content-center rounded-circle">
                                    <i class="ti ti-mail fs-xl"></i>
                                </div>
                                <p class="mb-0 fs-sm">
                                    Email
                                    <a href="mailto:hello@example.com"
                                        class="text-primary fw-semibold">{{ Auth::user()->email }}</a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <!-- end card-body-->
                </div>
                <!-- end card-->
            </div>
            <!-- end col-->

        </div>
        <!-- end row-->
    </div>
@endsection
