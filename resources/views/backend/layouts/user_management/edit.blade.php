@extends('backend.master')

@section('page_title', 'Edit User')

@section('content')

    <div class="px-3">
        <div class="row">
            <div class="col-12">


                <div class="card">
                    <div class="card-body">


                        <form method="POST" action="{{ route('admin.user.update', $user->id) }}"
                            enctype="multipart/form-data">

                            @csrf


                            <h5 class="mb-4 text-uppercase bg-light-subtle p-2 border rounded text-center">
                                <i class="ti ti-edit fs-lg"></i>
                                Edit User
                            </h5>


                            {{-- Name --}}
                            <div class="mb-3">
                                <label class="form-label">Full Name *</label>

                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $user->name) }}" required>
                            </div>


                            {{-- Username --}}
                            <div class="mb-3">
                                <label class="form-label">Username</label>

                                <input type="text" name="username" class="form-control"
                                    value="{{ old('username', $user->username) }}">
                            </div>


                            {{-- Email --}}
                            <div class="mb-3">
                                <label class="form-label">Email *</label>

                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $user->email) }}" required>
                            </div>


                            {{-- Phone --}}
                            <div class="mb-3">
                                <label class="form-label">Phone</label>

                                <input type="text" name="phone" class="form-control"
                                    value="{{ old('phone', $user->phone) }}">
                            </div>


                            {{-- Address --}}
                            <div class="mb-3">
                                <label class="form-label">Address</label>

                                <textarea name="address" class="form-control" rows="2">{{ old('address', $user->address) }}</textarea>
                            </div>


                            {{-- Location --}}
                            <div class="mb-3">
                                <label class="form-label">Location</label>

                                <input type="text" name="location" class="form-control"
                                    value="{{ old('location', $user->location) }}">
                            </div>


                            {{-- Role --}}
                            <div class="mb-3">
                                <label class="form-label">Role *</label>

                                <select name="role" class="form-select" required>

                                    @foreach ($roles as $role)
                                        <option value="{{ $role->name }}"
                                            {{ $user->hasRole($role->name) ? 'selected' : '' }}>

                                            {{ ucfirst($role->name) }}

                                        </option>
                                    @endforeach

                                </select>
                            </div>


                            {{-- Status --}}
                            <div class="mb-3">
                                <label class="form-label">Status</label>

                                <select name="status" class="form-select">

                                    <option value="active" {{ $user->status == 'active' ? 'selected' : '' }}>
                                        Active
                                    </option>

                                    <option value="inactive" {{ $user->status == 'inactive' ? 'selected' : '' }}>
                                        Inactive
                                    </option>

                                    <option value="banned" {{ $user->status == 'banned' ? 'selected' : '' }}>
                                        Banned
                                    </option>

                                </select>
                            </div>


                            {{-- Avatar --}}
                            <div class="mb-3">

                                <label>Profile Picture</label>

                                <input type="file" name="avatar" class="form-control" accept="image/*">

                                @if ($user->avatar)
                                    <div class="mt-2">
                                        <img src="{{ asset($user->avatar) }}" width="60" class="rounded-circle">
                                    </div>
                                @endif

                            </div>


                            {{-- Password --}}
                            <div class="mb-3">

                                <label>New Password (Optional)</label>

                                <input type="password" name="password" class="form-control">

                            </div>


                            <div class="mb-3">

                                <label>Confirm Password</label>

                                <input type="password" name="password_confirmation" class="form-control">

                            </div>


                            {{-- Submit --}}
                            <div class="text-end mt-4">

                                <button type="submit" class="btn btn-success px-4">

                                    Update User

                                </button>

                            </div>


                        </form>


                    </div>
                </div>

            </div>
        </div>
    </div>

@endsection




