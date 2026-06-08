<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Lock Screen | Sunfix Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link rel="shortcut icon" href="{{ asset('backend/assets/images/favicon.ico') }}" />

    <script src="{{ asset('backend/assets/js/config.js') }}"></script>
    <script src="{{ asset('backend/demo.js') }}"></script>

    <link href="{{ asset('backend/assets/css/vendors.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('backend/assets/css/app.min.css') }}" rel="stylesheet" />
</head>

<body>

    {{-- Top-right logout button --}}
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <a href="{{ route('logout') }}"
            class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1">
            <i class="ti ti-logout fs-md"></i>
            <span>Logout</span>
        </a>
    </div>

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

                        {{-- Error --}}
                        @if ($errors->any())
                            <div class="alert alert-danger mb-3">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        {{-- Brand / Logo --}}
                        <div class="auth-brand text-center mb-3">
                            <img src="{{ asset('backend/assets/images/logo-black.png') }}" height="40" />
                        </div>

                        <p class="text-center text-muted my-1 auth-line">
                            <span>Session Locked</span>
                        </p>

                        {{-- User Card --}}
                        <div class="text-center my-4">
                            <img src="{{ asset(Auth::user()->avatar == 'user.png' ? 'admin.png' : Auth::user()->avatar) }}"
                                alt="avatar"
                                class="rounded-circle border border-3 border-light shadow"
                                width="80" height="80"
                                style="object-fit: cover;" />
                            <h5 class="fw-bold mt-3 mb-0">{{ Auth::user()->name }}</h5>
                            <p class="text-muted fs-sm mb-0">{{ Auth::user()->email }}</p>
                        </div>

                        {{-- Unlock Form --}}
                        <form method="POST" action="{{ route('lock.screen.unlock') }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input
                                    type="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    placeholder="••••••••"
                                    required
                                    autofocus>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary fw-semibold py-2">
                                    <i class="ti ti-lock-open me-1"></i> Unlock
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    @include('backend.partial.sweetalert')

    <script src="{{ asset('backend/assets/js/vendors.min.js') }}"></script>
    <script src="{{ asset('backend/assets/js/app.js') }}"></script>

</body>

</html>
