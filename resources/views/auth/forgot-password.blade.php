<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Forgot Password | Sunfix Admin</title>
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

                        @if (session('status'))
                            <div class="alert alert-success mb-3">
                                {{ session('status') }}
                            </div>
                        @endif

                        <div class="text-center mb-3">
                            <h4 class="fw-bold text-dark">Forgot Password 🔐</h4>
                            <p class="text-muted">
                                Enter your email and we’ll send you a reset link.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('password.email') }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    placeholder="you@example.com" required>

                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-primary fw-semibold">
                                    Send Reset Link
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <a href="{{ route('login') }}" class="text-muted">
                                Back to Login
                            </a>
                        </div>

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
