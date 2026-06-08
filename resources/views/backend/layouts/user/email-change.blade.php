@extends('backend.master')

@section('page_title', 'Confirm Email Change')

@section('content')

<div class="px-3">
    <div class="row">
        <div class="col-md-6 offset-md-3">

            <div class="card">
                <div class="card-body">

                    {{-- Success Message --}}
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    {{-- Error Message --}}
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('email.change.confirm') }}">
                        @csrf

                        <!-- Token -->
                        <input type="hidden" name="token" value="{{ $token }}">

                        <!-- Current Password -->
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                placeholder="Enter your current password"
                                required>
                        </div>

                        <!-- OTP -->
                        <div class="mb-3">
                            <label class="form-label">OTP Code</label>
                            <input
                                type="text"
                                name="otp"
                                class="form-control"
                                placeholder="Enter OTP from email"
                                required>
                        </div>

                        <!-- Submit -->
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                Confirm & Update Email
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

@endsection
