<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Sign In | Sunfix Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link rel="shortcut icon" href="{{ asset('backend/assets/images/favicon.ico') }}" />

    <script src="{{ asset('backend/assets/js/config.js') }}"></script>
    <script src="{{ asset('backend/demo.js') }}"></script>

    <link href="{{ asset('backend/assets/css/vendors.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('backend/assets/css/app.min.css') }}" rel="stylesheet" />
</head>

<body>

    <div class="position-absolute top-0 end-0">
        <img src="{{ asset('backend/assets/images/auth-card-bg.svg') }}" class="auth-card-bg-img" />
    </div>

    <div class="position-absolute bottom-0 start-0" style="transform: rotate(180deg)">
        <img src="{{ asset('backend/assets/images/auth-card-bg.svg') }}" class="auth-card-bg-img" />
    </div>

    <div class="auth-box overflow-hidden align-items-center d-flex">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xxl-5 col-md-6 col-sm-8">
                    <div class="card p-4">

                        {{-- Session Status --}}
                        @if (session('status'))
                            <div class="alert alert-success mb-3">
                                {{ session('status') }}
                            </div>
                        @endif

                        <div class="auth-brand text-center mb-1">
                            <img src="{{ asset('backend/assets/images/logo-black.png') }}" height="40" />
                            <h4 class="fw-bold text-dark mt-3 text-uppercase">Great to see you again ethan 👋</h4>
                            <p class="text-muted w-lg-75 mx-auto">Let’s get you signed in. <br> Enter your email and
                                password
                                to continue.</p>
                        </div>

                        <p class="text-center text-muted my-1 auth-line"> <span> Welcome To Sunfix Admin Panel </span>
                        </p>

                        <form method="POST" action="{{ route('login') }}">
                            @csrf

                            {{-- Email --}}
                            <div class="mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Password --}}
                            <div class="mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password"
                                    class="form-control @error('password') is-invalid @enderror" placeholder="••••••••"
                                    required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Remember Me --}}
                            <div class="d-flex justify-content-end align-items-center mb-3">
                                <a href="{{ route('password.request') }}" class="text-decoration-underline text-muted">
                                    Forgot Password?
                                </a>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary fw-semibold py-2">
                                    Sign In
                                </button>
                            </div>
                        </form>
                    </div>

                    <p class="text-center text-muted mt-4 mb-0">
                        © {{ date('Y') }} Sunfix Admin
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- Jquery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    @include('backend.partial.sweetalert')

    <script src="{{ asset('backend/assets/js/vendors.min.js') }}"></script>
    <script src="{{ asset('backend/assets/js/app.js') }}"></script>


</body>

</html>
