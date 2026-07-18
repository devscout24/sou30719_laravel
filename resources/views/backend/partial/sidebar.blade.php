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
                        <span class="menu-text" data-lang="apps-chat">Dashboards</span>
                    </a>
                </li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse" href="#dashboards" aria-expanded="false" aria-controls="dashboards"
                        class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-wand"></i></span>
                        <span class="menu-text" data-lang="dashboards">Demo 01</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="dashboards" style="height: 100%;">
                        <ul class="sub-menu">
                            <li class="side-nav-item active">
                                <a href="index.html" class="side-nav-link">
                                    <span class="menu-text">Demo Dropdown 01</span>
                                </a>
                            </li>
                            <li class="side-nav-item active">
                                <a href="index.html" class="side-nav-link">
                                    <span class="menu-text">Demo Dropdown 02</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li class="side-nav-title mt-2" data-lang="main">Settings</li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.user.profile') ? 'active' : '' }}">
                    <a href="{{ route('admin.user.profile') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-user-circle"></i></span>
                        <span class="menu-text" data-lang="apps-chat">My Profile</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('system.settings') ? 'active' : '' }}">
                    <a href="{{ route('system.settings') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-settings"></i></span>
                        <span class="menu-text" data-lang="apps-chat">System Settings</span>
                    </a>
                </li>

                <li class="side-nav-item">
                    <a data-bs-toggle="collapse" href="#credentials" aria-expanded="false" aria-controls="credentials"
                        class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-key"></i></span>
                        <span class="menu-text" data-lang="credentials">Credentials Settings</span>
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
                                <a href="{{ route('system.settings.credential' , 'GoogleCloud') }}" class="side-nav-link">
                                    <span class="menu-text">Google Console Settings</span>
                                </a>
                            </li>
                        </ul>
                    </div>
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

                <li class="side-nav-item">
                    <a href="{{ route('dynamic.pages') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-code"></i></span>
                        <span class="menu-text" data-lang="apps-chat">Dynamic Pages</span>
                    </a>
                </li>

                <li class="side-nav-title mt-2" data-lang="main">Content & Community</li>

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

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.post-reports.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.post-reports.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-flag"></i></span>
                        <span class="menu-text">Post Reports</span>
                    </a>
                </li>

                <li class="side-nav-title mt-2" data-lang="main">Billing</li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.plans.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.plans.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-crown"></i></span>
                        <span class="menu-text">Subscription Plans</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.billing.subscriptions') ? 'active' : '' }}">
                    <a href="{{ route('admin.billing.subscriptions') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-users-group"></i></span>
                        <span class="menu-text">Subscriptions</span>
                    </a>
                </li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.billing.payments') ? 'active' : '' }}">
                    <a href="{{ route('admin.billing.payments') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-credit-card"></i></span>
                        <span class="menu-text">Payments</span>
                    </a>
                </li>

                <li class="side-nav-title mt-2" data-lang="main">Support</li>

                <li class="side-nav-item {{ Route::currentRouteNamed('admin.support-tickets.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.support-tickets.index') }}" class="side-nav-link">
                        <span class="menu-icon"><i class="ti ti-ticket"></i></span>
                        <span class="menu-text">Customer support</span>
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
