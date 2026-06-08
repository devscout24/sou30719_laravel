<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Reset Password | Sunfix Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link rel="shortcut icon" href="{{ asset('backend/assets/images/favicon.ico') }}" />
    <link href="{{ asset('backend/assets/css/vendors.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('backend/assets/css/app.min.css') }}" rel="stylesheet" />
</head>

<body>

    <div class="auth-box d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xxl-5 col-md-6 col-sm-8">
                    <div class="card p-4">

                        <div class="text-center mb-3">
                            <h4 class="fw-bold text-dark">Reset Password 🔑</h4>
                            <p class="text-muted">Set your new password below</p>
                        </div>

                        <form method="POST" action="{{ route('password.update') }}">
                            @csrf

                            <input type="hidden" name="token" value="{{ $token }}">
                            <input type="hidden" name="email" value="{{ $email }}">

                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="password"
                                    class="form-control @error('password') is-invalid @enderror" required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-primary fw-semibold">
                                    Reset Password
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Jquery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    @include('backend.partial.sweetalert')

</body>

</html>
