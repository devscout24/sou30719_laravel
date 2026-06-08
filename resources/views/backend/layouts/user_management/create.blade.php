@extends('backend.master')

@section('page_title', 'Create User')

@section('content')

    <div class="px-3">
        <div class="row">
            <div class="col-12">


                <div class="card">
                    <div class="card-body">


                        <form method="POST" action="{{ route('admin.user.store') }}" enctype="multipart/form-data">

                            @csrf


                            <h5 class="mb-4 text-uppercase bg-light-subtle p-2 border rounded text-center">
                                <i class="ti ti-user-plus fs-lg"></i>
                                Create New User
                            </h5>


                            {{-- Name --}}
                            <div class="mb-3">
                                <label class="form-label">Full Name *</label>

                                <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                                    required>
                            </div>


                            {{-- Username --}}
                            <div class="mb-3">
                                <label class="form-label">Username</label>

                                <input type="text" name="username" class="form-control" value="{{ old('username') }}">
                            </div>


                            {{-- Email --}}
                            <div class="mb-3">
                                <label class="form-label">Email *</label>

                                <input type="email" name="email" class="form-control" value="{{ old('email') }}"
                                    required>
                            </div>


                            {{-- Phone --}}
                            <div class="mb-3">
                                <label class="form-label">Phone</label>

                                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                            </div>


                            {{-- Address --}}
                            <div class="mb-3">
                                <label class="form-label">Address</label>

                                <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                            </div>


                            {{-- Location --}}
                            <div class="mb-3">
                                <label class="form-label">Location</label>

                                <input type="text" name="location" class="form-control" value="{{ old('location') }}">
                            </div>


                            {{-- Role --}}
                            <div class="mb-3">
                                <label class="form-label">User Role *</label>

                                <select name="role" class="form-select" required>

                                    <option value="">Select Role</option>

                                    @foreach ($roles as $role)
                                        <option value="{{ $role->name }}">
                                            {{ ucfirst($role->name) }}
                                        </option>
                                    @endforeach

                                </select>
                            </div>


                            {{-- Status --}}
                            <div class="mb-3">
                                <label class="form-label">Status</label>

                                <select name="status" class="form-select">

                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="banned">Banned</option>

                                </select>
                            </div>


                            {{-- Avatar --}}
                            <div class="mb-3">
                                <label class="form-label">Profile Picture</label>

                                <input type="file" name="avatar" class="form-control" accept="image/*">
                            </div>


                            {{-- Password --}}
                            <div class="mb-3">
                                <label class="form-label">Password *</label>

                                <input type="password" name="password" class="form-control" required>
                            </div>


                            {{-- Confirm --}}
                            <div class="mb-3">
                                <label class="form-label">Confirm Password *</label>

                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>


                            {{-- Submit --}}
                            <div class="text-end mt-4">

                                <button type="submit" class="btn btn-success px-4">
                                    Create User
                                </button>

                            </div>


                        </form>


                    </div>
                </div>

            </div>
        </div>
    </div>

@endsection



@push('scripts')
    <script>
        $(document).ready(function() {

            $('select[name="role"]').on('change', function() {

                let role = $(this).val();

                if (role === 'provider') {
                    $('#provider_section').slideDown();
                } else {
                    $('#provider_section').slideUp();
                }

            });

        });
    </script>
@endpush
