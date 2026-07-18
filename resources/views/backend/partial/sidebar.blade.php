<div class="sidenav-menu">
    <!-- Brand Logo -->
    <a href="{{ route('dashboard') }}" class="logo">
        <span class="logo logo-light">
            <span class="logo-lg"><img src="{{ asset('backend') }}/assets/images/logo.png" alt="logo" /></span>
            <span class="logo-sm"><img src="{{ asset('backend') }}/assets/images/logo-sm.png" alt="small logo" /></span>
        </span>

        <span class="logo logo-dark">
            <span class="logo-lg"><img src="{{ asset('backend') }}/assets/images/logo-black.png"
                    alt="dark logo" /></span>
            <span class="logo-sm"><img src="{{ asset('backend') }}/assets/images/logo-sm.png" alt="small logo" /></span>
        </span>
    </a>

    <!-- Sidebar Hover Menu Toggle Button -->
    <button class="button-on-hover">
        <i class="ti ti-circle align-middle"></i>
    </button>

    <!-- Full Sidebar Menu Close Button -->
    <button class="button-close-offcanvas">
        <i class="ti ti-menu-4 align-middle"></i>
    </button>

    <div class="scrollbar" data-simplebar="">

        <div id="user-profile-settings" class="sidenav-user" style="background: url(assets/images/user-bg-pattern.svg)">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="#!" class="link-reset">
                        <img src="assets/images/users/user-1.jpg" alt="user-image"
                            class="rounded-circle mb-2 avatar-md" />
                        <span class="sidenav-user-name fw-bold">David Dev</span>
                        <span class="fs-12 fw-semibold" data-lang="user-role">Art Director</span>
                    </a>
                </div>
                <div>
                    <a class="dropdown-toggle drop-arrow-none link-reset sidenav-user-set-icon"
                        data-bs-toggle="dropdown" data-bs-offset="0,12" href="#!" aria-haspopup="false"
                        aria-expanded="false">
                        <i class="ti ti-settings fs-24 align-middle ms-1"></i>
                    </a>

                    <div class="dropdown-menu">
                        <!-- Header -->
                        <div class="dropdown-header noti-title">
                            <h6 class="text-overflow m-0">Welcome back!</h6>
                        </div>

                        <!-- My Profile -->
                        <a href="#!" class="dropdown-item">
                            <i class="ti ti-user-circle me-1 fs-lg align-middle"></i>
                            <span class="align-middle">Profile</span>
                        </a>

                        <!-- Settings -->
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="ti ti-settings-2 me-1 fs-lg align-middle"></i>
                            <span class="align-middle">Account Settings</span>
                        </a>

                        <!-- Lock -->
                        <a href="auth-lock-screen.html" class="dropdown-item">
                            <i class="ti ti-lock me-1 fs-lg align-middle"></i>
                            <span class="align-middle">Lock Screen</span>
                        </a>

                        <!-- Logout -->
                        <a href="javascript:void(0);" class="dropdown-item text-danger fw-semibold">
                            <i class="ti ti-logout me-1 fs-lg align-middle"></i>
                            <span class="align-middle">Log Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!--- Sidenav Menu -->
        <div id="sidenav-menu">
            <ul class="side-nav">
                <li class="side-nav-title mt-2" data-lang="main">Main</li>

                <li class="side-nav-item {{ Route::currentRouteNamed('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-dashboard"></i></span>
                        <span class="menu-text" data-lang="apps-chat">Dashboard</span>
                    </a>
                </li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse" href="#users" aria-expanded="false" aria-controls="users"
                        class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-users"></i></span>
                        <span class="menu-text" data-lang="credentials">User Management</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="users" style="height: 100%;">
                        <ul class="sub-menu">
                            <li class="side-nav-item active">
                                <a href="{{ route('admin.user.lists') }}" class="side-nav-link">
                                    <span class="menu-text">Users List</span>
                                </a>
                            </li>
                            <li class="side-nav-item active">
                                <a href="{{ route('admin.user.create') }}" class="side-nav-link">
                                    <span class="menu-text">Create User</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li class="side-nav-title mt-2" data-lang="main">Content & Community</li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.social-feed.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.social-feed.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-message-2"></i></span>
                        <span class="menu-text">Social Feed</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.workspaces.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.workspaces.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-apps"></i></span>
                        <span class="menu-text">Workspaces</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.feed-topics.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.feed-topics.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-hash"></i></span>
                        <span class="menu-text">Feed Topics</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.posts.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.posts.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-news"></i></span>
                        <span class="menu-text">Posts</span>
                    </a>
                </li>

                <li
                    class="side-nav-item {{ Route::currentRouteNamed('admin.coming-soon') && request()->route('feature') === 'marketplace-ad' ? 'active' : '' }}">
                    <a href="{{ route('admin.coming-soon', 'marketplace-ad') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-shopping-cart"></i></span>
                        <span class="menu-text">Marketplace/Ad</span>
                        <span class="badge bg-secondary-subtle text-secondary badge-label ms-auto">Soon</span>
                    </a>
                </li>

                <li
                    class="side-nav-item {{ Route::currentRouteNamed('admin.coming-soon') && request()->route('feature') === 'event' ? 'active' : '' }}">
                    <a href="{{ route('admin.coming-soon', 'event') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-calendar-event"></i></span>
                        <span class="menu-text">Event</span>
                        <span class="badge bg-secondary-subtle text-secondary badge-label ms-auto">Soon</span>
                    </a>
                </li>

                <li
                    class="side-nav-item {{ Route::currentRouteNamed('admin.coming-soon') && request()->route('feature') === 'interest-hub' ? 'active' : '' }}">
                    <a href="{{ route('admin.coming-soon', 'interest-hub') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-heart"></i></span>
                        <span class="menu-text">Interest Hub</span>
                        <span class="badge bg-secondary-subtle text-secondary badge-label ms-auto">Soon</span>
                    </a>
                </li>

                <li
                    class="side-nav-item {{ Route::currentRouteNamed('admin.coming-soon') && request()->route('feature') === 'courier' ? 'active' : '' }}">
                    <a href="{{ route('admin.coming-soon', 'courier') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-truck-delivery"></i></span>
                        <span class="menu-text">Courier</span>
                        <span class="badge bg-secondary-subtle text-secondary badge-label ms-auto">Soon</span>
                    </a>
                </li>

                <li
                    class="side-nav-item {{ Route::currentRouteNamed('admin.coming-soon') && request()->route('feature') === 'cms' ? 'active' : '' }}">
                    <a href="{{ route('admin.coming-soon', 'cms') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-file-text"></i></span>
                        <span class="menu-text">CMS</span>
                        <span class="badge bg-secondary-subtle text-secondary badge-label ms-auto">Soon</span>
                    </a>
                </li>

                <li class="side-nav-title mt-2" data-lang="main">AI Operations</li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.llm-agent-log.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.llm-agent-log.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-robot"></i></span>
                        <span class="menu-text">LLM Agent Log</span>
                        <span class="badge bg-secondary-subtle text-secondary badge-label ms-auto">Soon</span>
                    </a>
                    <ul class="sub-menu" style="display: block;">
                        <li
                            class="side-nav-item {{ Route::currentRouteNamed('admin.llm-agent-log.transaction-cost') ? 'active' : '' }}">
                            <a href="{{ route('admin.llm-agent-log.transaction-cost') }}" class="side-nav-link">
                                <span class="menu-text">LLM transaction &amp; Cost</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="side-nav-title mt-2" data-lang="main">Billing</li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse" href="#subscription-management" aria-expanded="false"
                        aria-controls="subscription-management" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-crown"></i></span>
                        <span class="menu-text">Subscription Management</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="subscription-management" style="height: 100%;">
                        <ul class="sub-menu">
                            <li
                                class="side-nav-item {{ Route::currentRouteNamed('admin.billing.subscriptions') ? 'active' : '' }}">
                                <a href="{{ route('admin.billing.subscriptions') }}" class="side-nav-link">
                                    <span class="menu-text">Subscriptions</span>
                                </a>
                            </li>
                            <li class="side-nav-item {{ Route::currentRouteNamed('admin.plans.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.plans.index') }}" class="side-nav-link">
                                    <span class="menu-text">Subscription Plan</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.transactions.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.transactions.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-credit-card"></i></span>
                        <span class="menu-text">Transaction</span>
                    </a>
                </li>

                <li class="side-nav-title mt-2" data-lang="main">Support</li>

                <li class="side-nav-item {{ Route::currentRouteNamed('dynamic.pages*') ? 'active' : '' }}">
                    <a href="{{ route('dynamic.pages') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-code"></i></span>
                        <span class="menu-text" data-lang="apps-chat">Dynamic Pages</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.support-tickets.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.support-tickets.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-ticket"></i></span>
                        <span class="menu-text">Customer Support</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.post-reports.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.post-reports.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-flag"></i></span>
                        <span class="menu-text">Report Management</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.help-support.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.help-support.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-headset"></i></span>
                        <span class="menu-text">Help & Support</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.policies.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.policies.edit') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-file-text"></i></span>
                        <span class="menu-text">Disclaimers</span>
                    </a>
                </li>

                <li class="side-nav-title mt-2" data-lang="main">General Setting</li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.user.profile') ? 'active' : '' }}">
                    <a href="{{ route('admin.user.profile') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-user-circle"></i></span>
                        <span class="menu-text" data-lang="apps-chat">My Profile</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('system.settings') ? 'active' : '' }}">
                    <a href="{{ route('system.settings') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-settings"></i></span>
                        <span class="menu-text" data-lang="apps-chat">System Setting</span>
                    </a>
                </li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse" href="#credentials" aria-expanded="false" aria-controls="credentials"
                        class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-key"></i></span>
                        <span class="menu-text" data-lang="credentials">Credential Management</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="credentials" style="height: 100%;">
                        <ul class="sub-menu">
                            <li class="side-nav-item active">
                                <a href="{{ route('system.settings.credential' , 'Mail') }}" class="side-nav-link">
                                    <span class="menu-text">Mail Settings</span>
                                </a>
                            </li>
                            <li class="side-nav-item active">
                                <a href="{{ route('system.settings.credential' , 'Stripe') }}" class="side-nav-link">
                                    <span class="menu-text">Stripe Settings</span>
                                </a>
                            </li>
                            <li class="side-nav-item active">
                                <a href="{{ route('system.settings.credential' , 'GoogleCloud') }}"
                                    class="side-nav-link">
                                    <span class="menu-text">Google Console Settings</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li
                    class="side-nav-item {{ Route::currentRouteNamed('admin.coming-soon') && request()->route('feature') === 'admin-management' ? 'active' : '' }}">
                    <a href="{{ route('admin.coming-soon', 'admin-management') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-user-shield"></i></span>
                        <span class="menu-text">Admin Management</span>
                        <span class="badge bg-secondary-subtle text-secondary badge-label ms-auto">Soon</span>
                    </a>
                </li>

                <li class="side-nav-item text-danger">
                    <a href="{{ route('logout') }}" class="side-nav-link">
                        <span class="menu-icon text-danger"><i class="ti ti-logout"></i></span>
                        <span class="menu-text text-danger" data-lang="apps-chat">Log Out</span>
                    </a>
                </li>

            </ul>
        </div>
    </div>
</div>
