@extends('backend.master')

@section('page_title', 'System Settings')

@section('content')
    <div class="px-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <div class="mb-3">
                            <div class="alert alert-danger" role="alert">
                                <strong>Important Notice:</strong><br>
                                These settings contain highly sensitive system credentials related to email, payments,
                                and core services.
                                Entering incorrect information may cause serious system failures, including payment
                                issues and service downtime.
                                <br><br>
                                This feature is provided for flexibility. However, if you expect these credentials to
                                remain unchanged long-term,
                                please request your developer to disable this section to prevent accidental
                                misconfiguration.
                            </div>
                        </div>

                        <form action="{{ route('system.settings.credential.update') }}" method="POST">
                            @csrf

                            <input type="hidden" name="type" value="{{ $type }}">

                            {{-- ================= MAIL ================= --}}
                            @if ($type == 'Mail')
                                <h5 class="mb-3 text-uppercase bg-light p-2 text-center">
                                    Mail Configuration
                                </h5>

                                <div class="row">

                                    {{-- Mailer --}}
                                    <div class="col-md-6 mb-3">
                                        <label>Mailer</label>
                                        <input type="text" name="mail_mailer" class="form-control"
                                            value="{{ old('mail_mailer', $data['mail']['mailer'] ?? '') }}">
                                    </div>

                                    {{-- Host --}}
                                    <div class="col-md-6 mb-3">
                                        <label>Host</label>
                                        <input type="text" name="mail_host" class="form-control"
                                            value="{{ old('mail_host', $data['mail']['host'] ?? '') }}">
                                    </div>

                                    {{-- Port --}}
                                    <div class="col-md-6 mb-3">
                                        <label>Port</label>
                                        <input type="number" name="mail_port" class="form-control"
                                            value="{{ old('mail_port', $data['mail']['port'] ?? '') }}">
                                    </div>

                                    {{-- Username --}}
                                    <div class="col-md-6 mb-3">
                                        <label>Username</label>
                                        <input type="text" name="mail_username" class="form-control"
                                            value="{{ old('mail_username', $data['mail']['username'] ?? '') }}">
                                    </div>

                                    {{-- Password (Masked) --}}
                                    <div class="col-md-6 mb-3">
                                        <label>Password</label>
                                        <input type="password" name="mail_password" class="form-control"
                                            value="{{ mask_secret($data['mail']['password'] ?? '') }}">
                                    </div>

                                    {{-- Encryption --}}
                                    <div class="col-md-6 mb-3">
                                        <label>Encryption</label>
                                        <input type="text" name="mail_encryption" class="form-control"
                                            value="{{ old('mail_encryption', $data['mail']['encryption'] ?? '') }}">
                                    </div>

                                    {{-- From Address --}}
                                    <div class="col-md-6 mb-3">
                                        <label>From Address</label>
                                        <input type="email" name="mail_from_address" class="form-control"
                                            value="{{ old('mail_from_address', $data['mail']['from_addr'] ?? '') }}">
                                    </div>

                                    {{-- From Name --}}
                                    <div class="col-md-6 mb-3">
                                        <label>From Name</label>
                                        <input type="text" name="mail_from_name" class="form-control"
                                            value="{{ old('mail_from_name', $data['mail']['from_name'] ?? '') }}">
                                    </div>

                                </div>
                            @endif



                            {{-- ================= STRIPE ================= --}}
                            @if ($type == 'Stripe')
                                <h5 class="mb-3 text-uppercase bg-light p-2 text-center">
                                    Stripe Configuration
                                </h5>

                                <div class="row">

                                    {{-- Key --}}
                                    <div class="col-md-6 mb-3">
                                        <label>Stripe Key</label>
                                        <input type="text" name="stripe_key" class="form-control"
                                            value="{{ old('stripe_key', $data['stripe']['key'] ?? '') }}">
                                    </div>

                                    {{-- Secret (Masked) --}}
                                    <div class="col-md-6 mb-3">
                                        <label>Stripe Secret</label>
                                        <input type="password" name="stripe_secret" class="form-control"
                                            value="{{ mask_secret($data['stripe']['secret'] ?? '') }}">
                                    </div>

                                </div>
                            @endif



                            {{-- ================= GOOGLE CLOUD ================= --}}
                            @if ($type == 'GoogleCloud')
                                <h5 class="mb-3 text-uppercase bg-light p-2 text-center">
                                    Google Cloud Configuration
                                </h5>

                                <div class="row">

                                    <div class="col-md-6 mb-3">
                                        <label>Google App Key</label>
                                        <input type="text" name="google_app_key" class="form-control"
                                            value="{{ old('google_app_key', $data['google']['key'] ?? '') }}">
                                    </div>

                                </div>
                            @endif



                            {{-- ================= REVERB ================= --}}
                            @if ($type == 'Reverb')
                                <h5 class="mb-3 text-uppercase bg-light p-2 text-center">
                                    Reverb Configuration
                                </h5>

                                <div class="row">

                                    {{-- App ID --}}
                                    <div class="col-md-4 mb-3">
                                        <label>App ID</label>
                                        <input type="text" name="reverb_app_id" class="form-control"
                                            value="{{ old('reverb_app_id', $data['reverb']['id'] ?? '') }}">
                                    </div>

                                    {{-- App Key --}}
                                    <div class="col-md-4 mb-3">
                                        <label>App Key</label>
                                        <input type="text" name="reverb_app_key" class="form-control"
                                            value="{{ old('reverb_app_key', $data['reverb']['key'] ?? '') }}">
                                    </div>

                                    {{-- Secret (Masked) --}}
                                    <div class="col-md-4 mb-3">
                                        <label>App Secret</label>
                                        <input type="password" name="reverb_app_secret" class="form-control"
                                            value="{{ mask_secret($data['reverb']['secret'] ?? '') }}">
                                    </div>

                                </div>
                            @endif


                            <!-- Password -->
                            <div class="mt-3 mb-3">
                                <label class="form-label">
                                    Password
                                    <span class="text-muted" style="font-size:10px;font-style:italic;">
                                        (Enter current password to save)
                                    </span>
                                </label>

                                <input type="password" name="current_password" class="form-control" required
                                    placeholder="Enter current password">
                            </div>



                            {{-- ================= SUBMIT ================= --}}
                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary px-4">
                                    Save Settings
                                </button>
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
