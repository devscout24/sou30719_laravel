@extends('backend.master')

@section('page_title', 'User Details')

@section('content')

    @php
        $avatarUrl = asset($user->avatar && $user->avatar !== 'user.png' ? $user->avatar : 'admin.png');
        $activePlanName = optional(optional($user->activeSubscription)->plan)->name ?? 'Free';
        $datingProfile = $user->datingProfile;
        $datingPreference = $user->datingPreference;
        $primaryPhoto = null;
        if ($datingProfile) {
            $primary = $datingProfile->images->firstWhere('is_primary', true) ?? $datingProfile->images->first();
            $primaryPhoto = $primary?->full_url;
        }
    @endphp

    <a href="{{ route('admin.user.lists') }}" class="d-inline-flex align-items-center gap-1 text-muted mb-3">
        <i class="ti ti-arrow-left fs-lg"></i> Back to User Management
    </a>

    <div class="row">

        {{-- Profile Card --}}
        <div class="col-xl-4">
            <div class="card card-top-sticky">
                <div class="card-body">

                    <div class="d-flex align-items-center mb-4">
                        <div class="me-3 position-relative">
                            <img src="{{ $primaryPhoto ?? $avatarUrl }}" class="rounded-circle" width="72"
                                height="72" style="object-fit: cover;" alt="Avatar">
                        </div>

                        <div>
                            <h5 class="mb-0">{{ $user->name ?? 'N/A' }}</h5>
                            @if ($user->username)
                                <span class="text-muted fs-sm">{{ '@' . $user->username }}</span>
                            @endif
                            <div class="mt-1">
                                <span class="badge text-bg-light badge-label">
                                    {{ $user->getRoleNames()->first() ?? 'No Role' }}
                                </span>
                            </div>
                        </div>

                        <div class="ms-auto">
                            <div class="dropdown">
                                <a href="#" class="btn btn-icon btn-ghost-light text-muted" data-bs-toggle="dropdown">
                                    <i class="ti ti-dots-vertical fs-xl"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('admin.user.edit', $user->id) }}">
                                            <i class="ti ti-edit fs-sm me-1 align-middle"></i> Edit User
                                        </a>
                                    </li>
                                    <li>
                                        <form action="{{ route('admin.user.destroy', $user->id) }}" method="POST"
                                            class="delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="ti ti-trash fs-sm me-1 align-middle"></i> Delete User
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    @if ($user->bio)
                        <p class="text-muted fs-sm">{{ $user->bio }}</p>
                    @endif

                    <div class="d-flex align-items-center gap-4 mb-3">
                        <div>
                            <span class="text-muted fs-xs d-block">Connections</span>
                            <span class="fw-semibold">{{ number_format($connectionsCount) }}</span>
                        </div>
                        <div>
                            <span class="text-muted fs-xs d-block">Posts</span>
                            <span class="fw-semibold">{{ number_format($postsCount) }}</span>
                        </div>
                    </div>

                    @if ($user->status === 'banned')
                        <form action="{{ route('admin.user.status.update', $user->id) }}" method="POST"
                            class="block-user-form">
                            @csrf
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn btn-success-subtle text-success w-100"
                                data-confirm-title="Unblock user?"
                                data-confirm-text="This will restore the user's access to their account.">
                                Unblock user
                            </button>
                        </form>
                    @else
                        <form action="{{ route('admin.user.status.update', $user->id) }}" method="POST"
                            class="block-user-form">
                            @csrf
                            <input type="hidden" name="status" value="banned">
                            <button type="submit" class="btn btn-danger-subtle text-danger w-100"
                                data-confirm-title="Block user?"
                                data-confirm-text="Are you sure you want to block this user?">
                                Block user
                            </button>
                        </form>
                    @endif

                </div>
            </div>
        </div>

        {{-- User Info Card --}}
        <div class="col-xl-8">
            <div class="card card-top-sticky">
                <div class="card-header">
                    <h5 class="mb-0">User info</h5>
                </div>
                <div class="card-body">
                    <div class="row row-gap-3">
                        <div class="col-md-4">
                            <span class="text-muted fs-xs d-block">Gender</span>
                            <span class="fw-medium">{{ $user->gender ? ucfirst($user->gender) : '—' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-xs d-block">Plan</span>
                            <span class="fw-medium">{{ $activePlanName }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-xs d-block">Country</span>
                            <span class="fw-medium">{{ $user->country ?: '—' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-xs d-block">Phone number</span>
                            <span class="fw-medium">{{ $user->phone ?: '—' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-xs d-block">Last active on</span>
                            <span class="fw-medium">
                                {{ $user->last_login_at ? $user->last_login_at->format('d M Y, h:i A') : '—' }}
                            </span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-xs d-block">Joined</span>
                            <span class="fw-medium">{{ optional($user->created_at)->format('d M Y') ?? '—' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-xs d-block">Status</span>
                            @if ($user->status == 'active')
                                <span class="badge bg-success-subtle text-success">Active</span>
                            @elseif($user->status == 'inactive')
                                <span class="badge bg-warning-subtle text-warning">Inactive</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger">Banned</span>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-xs d-block">Verified</span>
                            @if ($user->email_verified_at)
                                <span class="badge bg-success-subtle text-success">Verified</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Unverified</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabs Card --}}
            <div class="card">
                <div class="card-header card-tabs">
                    <ul class="nav nav-tabs card-header-tabs nav-bordered flex-wrap row-gap-1">
                        <li class="nav-item">
                            <a href="#basic-info" data-bs-toggle="tab" class="nav-link active">Basic info</a>
                        </li>
                        <li class="nav-item">
                            <a href="#gallery" data-bs-toggle="tab" class="nav-link">Gallery</a>
                        </li>
                        <li class="nav-item">
                            <a href="#dating-preference" data-bs-toggle="tab" class="nav-link">Dating preference</a>
                        </li>
                        <li class="nav-item">
                            <a href="#subscriptions" data-bs-toggle="tab" class="nav-link">Subscriptions</a>
                        </li>
                        <li class="nav-item">
                            <a href="#transactions" data-bs-toggle="tab" class="nav-link">Transactions</a>
                        </li>
                        <li class="nav-item">
                            <a href="#tickets" data-bs-toggle="tab" class="nav-link">Tickets</a>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content">

                        {{-- Basic Info --}}
                        <div class="tab-pane show active" id="basic-info">
                            @if ($datingProfile)
                                <div class="text-center">
                                    <img src="{{ $primaryPhoto ?? $avatarUrl }}" class="rounded-circle mb-3"
                                        width="96" height="96" style="object-fit: cover;" alt="Profile photo">

                                    <h5 class="mb-0">
                                        {{ $datingProfile->dating_full_name ?? $user->name }}
                                        @if ($datingProfile->nickname)
                                            <span class="text-muted fw-normal">({{ $datingProfile->nickname }})</span>
                                        @endif
                                    </h5>

                                    @if ($datingProfile->city)
                                        <p class="text-muted mb-2">{{ $datingProfile->city }}</p>
                                    @endif

                                    @if ($datingProfile->about)
                                        <p class="mx-auto" style="max-width: 560px;">{{ $datingProfile->about }}</p>
                                    @elseif ($datingProfile->about_me)
                                        <p class="mx-auto" style="max-width: 560px;">{{ $datingProfile->about_me }}</p>
                                    @endif

                                    @if (!empty($datingProfile->hobbies))
                                        <div class="d-flex flex-wrap justify-content-center gap-1 mt-2">
                                            @foreach ($datingProfile->hobbies as $hobby)
                                                <span class="badge bg-light text-body border">{{ $hobby }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="text-center text-muted py-5">
                                    <i class="ti ti-user-question fs-2 d-block mb-2"></i>
                                    This user hasn't completed their dating profile yet.
                                </div>
                            @endif
                        </div>

                        {{-- Gallery --}}
                        <div class="tab-pane" id="gallery">
                            @if ($datingProfile && $datingProfile->images->count())
                                <h6 class="text-uppercase fs-xs text-muted mb-2">Dating Photos</h6>
                                <div class="row g-2 mb-3">
                                    @foreach ($datingProfile->images as $image)
                                        <div class="col-6 col-md-3">
                                            <img src="{{ $image->full_url }}" class="rounded w-100"
                                                style="height: 160px; object-fit: cover;" alt="Photo">
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($user->galleryImages->count())
                                <h6 class="text-uppercase fs-xs text-muted mb-2">Gallery</h6>
                                <div class="row g-2">
                                    @foreach ($user->galleryImages as $image)
                                        <div class="col-6 col-md-3">
                                            <img src="{{ $image->full_url }}" class="rounded w-100"
                                                style="height: 160px; object-fit: cover;" alt="Photo">
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if (!($datingProfile && $datingProfile->images->count()) && !$user->galleryImages->count())
                                <div class="text-center text-muted py-5">
                                    <i class="ti ti-photo-off fs-2 d-block mb-2"></i>
                                    No photos uploaded yet.
                                </div>
                            @endif
                        </div>

                        {{-- Dating preference --}}
                        <div class="tab-pane" id="dating-preference">

                            <ul class="nav nav-pills bg-light-subtle rounded p-1 mb-3 flex-wrap row-gap-1">
                                <li class="nav-item">
                                    <a href="#identity-location" data-bs-toggle="tab"
                                        class="nav-link active text-nowrap">Identity & Location</a>
                                </li>
                                <li class="nav-item">
                                    <a href="#visual-info" data-bs-toggle="tab" class="nav-link text-nowrap">Visual
                                        info</a>
                                </li>
                                <li class="nav-item">
                                    <a href="#appearance-lifestyle" data-bs-toggle="tab"
                                        class="nav-link text-nowrap">Appearance & Lifestyle</a>
                                </li>
                                <li class="nav-item">
                                    <a href="#interests-personality" data-bs-toggle="tab"
                                        class="nav-link text-nowrap">Interests & Personality</a>
                                </li>
                                <li class="nav-item">
                                    <a href="#matching-criteria" data-bs-toggle="tab"
                                        class="nav-link text-nowrap">Matching criteria</a>
                                </li>
                                <li class="nav-item">
                                    <a href="#knowledge-base" data-bs-toggle="tab" class="nav-link text-nowrap">
                                        <i class="ti ti-sparkles me-1"></i> Knowledge base
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content">

                                {{-- Identity & Location --}}
                                <div class="tab-pane show active" id="identity-location">
                                    @if ($datingProfile)
                                        <div class="row row-gap-3">
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Nick Name</span>
                                                <span class="fw-medium">{{ $datingProfile->dating_nickname ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Status</span>
                                                <span
                                                    class="fw-medium">{{ $datingProfile->relationship_status ? ucfirst($datingProfile->relationship_status) : '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Date of birth</span>
                                                <span
                                                    class="fw-medium">{{ $datingProfile->dating_dob ? $datingProfile->dating_dob->format('F Y') : '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Gender</span>
                                                <span
                                                    class="fw-medium">{{ $datingProfile->dating_gender ? ucfirst($datingProfile->dating_gender) : '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Location</span>
                                                <span class="fw-medium">{{ $datingProfile->dating_location ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Country</span>
                                                <span class="fw-medium">{{ $datingProfile->dating_country ?: '—' }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-center text-muted py-4">No dating profile data yet.</div>
                                    @endif
                                </div>

                                {{-- Visual info --}}
                                <div class="tab-pane" id="visual-info">
                                    @if ($visualKnowledgeItems->count())
                                        <div class="row g-3">
                                            @foreach ($visualKnowledgeItems as $item)
                                                <div class="col-md-6">
                                                    <div class="d-flex gap-3">
                                                        <img src="{{ $item->full_image_url }}" class="rounded"
                                                            width="90" height="90" style="object-fit: cover;"
                                                            alt="Visual">
                                                        <div>
                                                            <span class="text-muted fs-xs d-block">Description</span>
                                                            <p class="mb-0 fs-sm">{{ $item->content }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-muted py-4">No visual info recorded for this
                                            user yet.</div>
                                    @endif
                                </div>

                                {{-- Appearance & Lifestyle --}}
                                <div class="tab-pane" id="appearance-lifestyle">
                                    @if ($datingProfile)
                                        <div class="row row-gap-3">
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Height</span>
                                                <span class="fw-medium">{{ $datingProfile->height ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Occupation/Education</span>
                                                <span
                                                    class="fw-medium">{{ trim(($datingProfile->occupation ?? '') . (($datingProfile->occupation && $datingProfile->education) ? ' / ' : '') . ($datingProfile->education ?? '')) ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Lifestyle & Habits</span>
                                                <span
                                                    class="fw-medium">{{ $datingProfile->lifestyle_habits ? ucfirst($datingProfile->lifestyle_habits) : '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Body Type</span>
                                                <span class="fw-medium">{{ $datingProfile->body_type ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Ethnicity</span>
                                                <span class="fw-medium">{{ $datingProfile->ethnicity ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Religious beliefs</span>
                                                <span
                                                    class="fw-medium">{{ $datingProfile->religious_beliefs ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Languages</span>
                                                <span
                                                    class="fw-medium">{{ !empty($datingProfile->languages) ? implode(', ', $datingProfile->languages) : '—' }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-center text-muted py-4">No appearance & lifestyle data yet.
                                        </div>
                                    @endif
                                </div>

                                {{-- Interests & Personality --}}
                                <div class="tab-pane" id="interests-personality">
                                    @if ($datingProfile)
                                        <div class="row row-gap-3">
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Interests/Hobbies</span>
                                                <span
                                                    class="fw-medium">{{ !empty($datingProfile->hobbies) ? implode(', ', $datingProfile->hobbies) : '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Values/Personality Traits</span>
                                                <span
                                                    class="fw-medium">{{ !empty($datingProfile->personality_traits) ? implode(', ', $datingProfile->personality_traits) : '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Pet Preferences</span>
                                                <span
                                                    class="fw-medium">{{ $datingProfile->pet_preference ? ucfirst(str_replace('_', ' ', $datingProfile->pet_preference)) : '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Political Views</span>
                                                <span
                                                    class="fw-medium">{{ $datingProfile->political_views ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Family plans</span>
                                                <span
                                                    class="fw-medium">{{ $datingProfile->family_plans ? ucfirst(str_replace('_', ' ', $datingProfile->family_plans)) : '—' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="text-muted fs-xs d-block">Children</span>
                                                <span
                                                    class="fw-medium">{{ $datingProfile->children_status ? ucfirst(str_replace('_', ' ', $datingProfile->children_status)) : '—' }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-center text-muted py-4">No interests & personality data yet.
                                        </div>
                                    @endif
                                </div>

                                {{-- Matching criteria --}}
                                <div class="tab-pane" id="matching-criteria">
                                    @if ($datingPreference)
                                        <div class="row row-gap-3">
                                            <div class="col-md-6">
                                                <span class="text-muted fs-xs d-block">Relationship Goals</span>
                                                <span
                                                    class="fw-medium">{{ $datingPreference->relationship_goal ? ucfirst(str_replace('_', ' ', $datingPreference->relationship_goal)) : '—' }}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="text-muted fs-xs d-block">Deal Breakers</span>
                                                <span class="fw-medium">{{ $datingPreference->deal_breakers ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-12">
                                                <span class="text-muted fs-xs d-block">Partner Preferences</span>
                                                <span
                                                    class="fw-medium">{{ $datingPreference->partner_preferences ?: '—' }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-center text-muted py-4">No matching criteria data yet.</div>
                                    @endif
                                </div>

                                {{-- Knowledge base --}}
                                <div class="tab-pane" id="knowledge-base">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <div
                                            class="avatar-sm bg-dark text-white rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="ti ti-sparkles fs-lg"></i>
                                        </div>
                                        <h5 class="mb-0">Artificial Intelligence (AI) Knowledge Base</h5>
                                    </div>

                                    @if ($textKnowledgeItems->count())
                                        <div class="list-group">
                                            @foreach ($textKnowledgeItems as $item)
                                                <div class="list-group-item">{{ $item->content }}</div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-muted mb-0">AI info will be here.</p>
                                    @endif
                                </div>

                            </div>
                        </div>

                        {{-- Subscriptions --}}
                        <div class="tab-pane" id="subscriptions">
                            <div class="bg-light-subtle rounded p-3 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                                <div>
                                    <span class="text-muted fs-xs text-uppercase">Current plan</span>
                                    <h4 class="mb-0">{{ $activePlanName }}</h4>
                                    @if ($user->activeSubscription && $user->activeSubscription->end_date)
                                        <span class="text-muted fs-sm">Renews on
                                            {{ $user->activeSubscription->end_date->format('M d, Y') }}</span>
                                    @endif
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary" disabled
                                        title="Coming soon" onclick="Swal.fire('Coming soon', 'Managing a user\'s plan from the admin panel isn\'t available yet.', 'info')">
                                        Manage Plan
                                    </button>
                                    <button type="button" class="btn btn-dark" disabled title="Coming soon"
                                        onclick="Swal.fire('Coming soon', 'Upgrading a user\'s plan from the admin panel isn\'t available yet.', 'info')">
                                        Upgrade to Pro
                                    </button>
                                </div>
                            </div>

                            <h6 class="mb-2">Billing History</h6>
                            @if ($user->payments->count())
                                <div class="list-group">
                                    @foreach ($user->payments as $payment)
                                        <div
                                            class="list-group-item d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="ti ti-file-invoice fs-lg text-muted"></i>
                                                <div>
                                                    <span class="d-block fw-medium">
                                                        {{ optional(optional($payment->subscription)->plan)->name ?? 'Subscription' }}
                                                        payment
                                                    </span>
                                                    <span class="text-muted fs-xs">
                                                        {{ optional($payment->paid_at ?? $payment->created_at)->format('M d, Y') ?? '—' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <span class="fw-semibold">{{ number_format($payment->amount, 2) }}
                                                {{ strtoupper($payment->currency) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-muted py-4">No billing history yet.</div>
                            @endif
                        </div>

                        {{-- Transactions --}}
                        <div class="tab-pane" id="transactions">
                            @if ($user->payments->count())
                                <div class="table-responsive">
                                    <table class="table table-custom table-centered table-hover w-100 mb-0">
                                        <thead class="bg-light align-middle bg-opacity-25 thead-sm text-nowrap">
                                            <tr class="text-uppercase fs-xxs">
                                                <th>ID</th>
                                                <th>Date</th>
                                                <th>Subject</th>
                                                <th>Method</th>
                                                <th>Amount</th>
                                                <th>Tax</th>
                                                <th>Status</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($user->payments as $payment)
                                                <tr>
                                                    <td>#{{ $payment->id }}</td>
                                                    <td>{{ optional($payment->created_at)->format('d M Y, h:i A') ?? '—' }}</td>
                                                    <td>
                                                        {{ optional(optional($payment->subscription)->plan)->name ?? 'Subscription' }}
                                                    </td>
                                                    <td>{{ $payment->stripe_payment_intent_id ? 'Stripe' : '—' }}</td>
                                                    <td>{{ number_format($payment->amount, 2) }}
                                                        {{ strtoupper($payment->currency) }}</td>
                                                    <td>—</td>
                                                    <td>
                                                        @php
                                                            $statusColors = [
                                                                'paid' => 'success',
                                                                'pending' => 'warning',
                                                                'failed' => 'danger',
                                                                'refunded' => 'secondary',
                                                            ];
                                                            $sc = $statusColors[$payment->status] ?? 'secondary';
                                                        @endphp
                                                        <span
                                                            class="badge bg-{{ $sc }}-subtle text-{{ $sc }}">{{ ucfirst($payment->status) }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        @if ($payment->receipt_url)
                                                            <a href="{{ $payment->receipt_url }}" target="_blank"
                                                                class="btn btn-default btn-icon btn-sm">
                                                                <i class="ti ti-file-invoice fs-lg"></i>
                                                            </a>
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center text-muted py-4">No transactions yet.</div>
                            @endif
                        </div>

                        {{-- Tickets --}}
                        <div class="tab-pane" id="tickets">
                            @if ($user->supportTickets->count())
                                @foreach ($user->supportTickets as $ticket)
                                    @php
                                        $ticketColors = [
                                            'open' => 'warning',
                                            'in_progress' => 'info',
                                            'resolved' => 'success',
                                            'closed' => 'secondary',
                                        ];
                                        $ticketLabels = [
                                            'open' => 'Pending',
                                            'in_progress' => 'On-going',
                                            'resolved' => 'Resolved',
                                            'closed' => 'Closed',
                                        ];
                                        $tc = $ticketColors[$ticket->status] ?? 'secondary';
                                    @endphp
                                    <div class="border rounded p-3 mb-2">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <span
                                                    class="badge bg-{{ $tc }}-subtle text-{{ $tc }} me-2">{{ $ticketLabels[$ticket->status] ?? ucfirst($ticket->status) }}</span>
                                                <strong>Ticket# {{ $ticket->id }}</strong>
                                            </div>
                                            <span
                                                class="text-muted fs-xs">Posted at {{ optional($ticket->created_at)->format('jS M, Y, h:i A') ?? '—' }}</span>
                                        </div>
                                        <h6 class="mt-2 mb-1">{{ $ticket->subject }}</h6>
                                        <p class="text-muted fs-sm mb-2">{{ \Illuminate\Support\Str::limit($ticket->message, 220) }}</p>
                                        <a href="{{ route('admin.support-tickets.show', $ticket->id) }}"
                                            class="fw-semibold text-primary fs-sm">Open Ticket</a>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center text-muted py-4">No support tickets yet.</div>
                            @endif
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).on('submit', '.block-user-form', function(e) {
            e.preventDefault();
            let form = this;

            Swal.fire({
                title: $(form).find('button[type=submit]').data('confirm-title'),
                text: $(form).find('button[type=submit]').data('confirm-text'),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    </script>
@endpush
