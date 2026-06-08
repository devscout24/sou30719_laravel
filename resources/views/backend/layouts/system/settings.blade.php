@extends('backend.master')

@section('page_title', 'System Settings')

@section('content')


    <div class="px-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('system.settings.update') }}" enctype="multipart/form-data">
                            @csrf

                            <!-- Company Info -->
                            <h5
                                class="mb-3 text-uppercase bg-light-subtle p-1 border-dashed border rounded border-light d-flex justify-content-center align-items-center gap-1">
                                <i class="ti ti-building fs-lg"></i>
                                Company Info
                            </h5>

                            <div class="row">

                                <!-- Company Name -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Company Name</label>
                                        <input type="text" name="companyname" class="form-control"
                                            value="{{ old('companyname', $settings->company_name ?? '') }}"
                                            placeholder="Enter company name">
                                    </div>
                                </div>

                                <!-- Website -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Website</label>
                                        <input type="text" name="cwebsite" class="form-control"
                                            value="{{ old('cwebsite', $settings->website ?? '') }}"
                                            placeholder="https://yourcompany.com/">
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="text" name="cemail" class="form-control"
                                            value="{{ old('cemail', $settings->email ?? '') }}"
                                            placeholder="Enter email address">
                                    </div>
                                </div>

                                <!-- Hotline -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Hotline</label>
                                        <input type="text" name="chotline" class="form-control"
                                            value="{{ old('chotline', $settings->hotline ?? '') }}"
                                            placeholder="Enter hotline number">
                                    </div>
                                </div>

                                <!-- Address -->
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <input type="text" name="address" class="form-control"
                                            value="{{ old('address', $settings->address ?? '') }}"
                                            placeholder="Enter address">
                                    </div>
                                </div>

                                <!-- Description -->
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="4">{{ old('description', $settings->description ?? '') }}</textarea>
                                    </div>
                                </div>

                                <!-- Logo -->
                                <div class="col-md-12 logo-preview-light" id="logoPreviewBox">

                                    <div class="mb-4">

                                        <label class="form-label d-flex align-items-center justify-content-between">

                                            <span>Company Logo</span>

                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" id="logoBgToggle">
                                                <label class="form-check-label" for="logoBgToggle">
                                                    Dark Preview
                                                </label>
                                            </div>

                                        </label>

                                        <input type="file" name="company_logo" class="form-control dropify"
                                            data-height="150"
                                            data-default-file="{{ $settings && $settings->logo ? asset($settings->logo) : '' }}">

                                    </div>

                                </div>


                                <!-- Play Store -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            APP Download ( <i class="ti ti-brand-google-play"></i> Play Store )
                                        </label>

                                        <input type="text" name="downloadLinkPlay" class="form-control"
                                            value="{{ old('downloadLinkPlay', $settings->play_store_link ?? '') }}"
                                            placeholder="Play Store link">
                                    </div>
                                </div>

                                <!-- Apple Store -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            APP Download ( <i class="ti ti-brand-apple"></i> Apple Store )
                                        </label>

                                        <input type="text" name="downloadLinkApple" class="form-control"
                                            value="{{ old('downloadLinkApple', $settings->apple_store_link ?? '') }}"
                                            placeholder="Apple Store link">
                                    </div>
                                </div>

                            </div>


                            <!-- Social -->
                            <h5
                                class="mb-3 text-uppercase bg-light-subtle p-1 border-dashed border rounded border-light d-flex justify-content-center align-items-center gap-1">
                                <i class="ti ti-world fs-lg"></i>
                                Social
                            </h5>

                            <div class="row g-3">

                                <!-- Facebook -->
                                <div class="col-md-6">
                                    <label class="form-label">Facebook</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-brand-facebook"></i>
                                        </span>
                                        <input type="text" name="social_fb" class="form-control"
                                            value="{{ old('social_fb', $settings->facebook ?? '') }}"
                                            placeholder="Facebook URL">
                                    </div>
                                </div>

                                <!-- LinkedIn -->
                                <div class="col-md-6">
                                    <label class="form-label">LinkedIn</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-brand-linkedin"></i>
                                        </span>
                                        <input type="text" name="social_ln" class="form-control"
                                            value="{{ old('social_ln', $settings->linkedin ?? '') }}"
                                            placeholder="LinkedIn URL">
                                    </div>
                                </div>

                                <!-- YouTube -->
                                <div class="col-md-6">
                                    <label class="form-label">YouTube</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-brand-youtube"></i>
                                        </span>
                                        <input type="text" name="social_yt" class="form-control"
                                            value="{{ old('social_yt', $settings->youtube ?? '') }}"
                                            placeholder="YouTube URL">
                                    </div>
                                </div>

                                <!-- Twitter -->
                                <div class="col-md-6">
                                    <label class="form-label">Twitter (X)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-brand-x"></i>
                                        </span>
                                        <input type="text" name="social_tw" class="form-control"
                                            value="{{ old('social_tw', $settings->twitter ?? '') }}"
                                            placeholder="@username">
                                    </div>
                                </div>

                                <!-- TikTok -->
                                <div class="col-md-6">
                                    <label class="form-label">TikTok</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-brand-tiktok"></i>
                                        </span>
                                        <input type="text" name="social_tt" class="form-control"
                                            value="{{ old('social_tt', $settings->tiktok ?? '') }}"
                                            placeholder="@username">
                                    </div>
                                </div>

                                <!-- Threads -->
                                <div class="col-md-6">
                                    <label class="form-label">Threads</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-brand-threads"></i>
                                        </span>
                                        <input type="text" name="social_th" class="form-control"
                                            value="{{ old('social_th', $settings->threads ?? '') }}"
                                            placeholder="@username">
                                    </div>
                                </div>

                            </div>


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


                            <!-- Submit -->
                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-success">
                                    Save Changes
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
