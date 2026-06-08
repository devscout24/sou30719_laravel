@extends('backend.master')

@section('page_title', 'Edit Profile')

@section('content')
    <div class="px-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.user.profile.update') }}"
                            enctype="multipart/form-data">
                            @csrf

                            <!-- Personal Info -->
                            @if ($type === 'profile')
                                <input type="hidden" name="type" value="profile">

                                <h5
                                    class="mb-3 text-uppercase bg-light-subtle p-1 border-dashed border rounded border-light d-flex justify-content-center align-items-center gap-1">
                                    <i class="ti ti-user-circle fs-lg"></i>
                                    Personal Info
                                </h5>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                        placeholder="Enter first name" value="{{ Auth::user()->name }}" />
                                </div>

                                <div class="mb-4">
                                    <label for="profilephoto" class="form-label">Profile Photo</label>
                                    <input type="file" id="profilephoto" name="profile_photo"
                                        class="form-control dropify" data-height="150"
                                        data-default-file="{{ asset(Auth::user()->avatar == 'user.png' ? 'admin.png' : Auth::user()->avatar) }}" />
                                </div>
                            @endif

                            @if ($type === 'password')
                                <input type="hidden" name="type" value="password">

                                <h5
                                    class="mb-3 text-uppercase bg-light-subtle p-1 border-dashed border rounded border-light d-flex justify-content-center align-items-center gap-1">
                                    <i class="ti ti-lock fs-lg"></i>
                                    Change Password
                                </h5>

                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="text" name="new_password" class="form-control" id="password"
                                        placeholder="Enter new password" />
                                </div>

                                <div class="mb-3">
                                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                                    <input type="text" class="form-control" name="new_password_confirmation"
                                        id="password_confirmation" placeholder="Enter confirm password" />
                                </div>
                            @endif

                            @if ($type === 'email')
                                <input type="hidden" name="type" value="email">

                                <h5
                                    class="mb-3 text-uppercase bg-light-subtle p-1 border-dashed border rounded border-light d-flex justify-content-center align-items-center gap-1">
                                    <i class="ti ti-mail fs-lg"></i>
                                    Change Email
                                </h5>

                                <div class="mb-3">
                                    <label for="email" class="form-label">New Email</label>
                                    <input type="text" class="form-control" name="new_email" id="email"
                                        placeholder="Enter new email" />
                                </div>
                            @endif

                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span
                                        style="font-size: 10px; font-style:italic;" name="current_password"
                                        class="text-muted">( To make the changes
                                        you have to enter your current password)</span></label>
                                <input type="password" class="form-control" name="current_password" id="password"
                                    placeholder="Enter current password" />
                            </div>

                            <!-- Submit -->
                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-success">Save Changes</button>
                            </div>

                        </form>
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
