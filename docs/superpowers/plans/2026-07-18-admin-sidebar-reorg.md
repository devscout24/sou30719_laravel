# Admin Sidebar Reorganization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure the backend admin sidebar into six sections (Main, Content & Community, AI Operations, Billing, Support, General Setting), reusing existing routes, plus a new generic "Coming Soon" placeholder page for six not-yet-built feature entries.

**Architecture:** Two pieces: (1) a new generic Coming Soon route/controller/view reused by six sidebar links, whitelisting the `feature` slug so unknown values 404 instead of being reflected as content; (2) a rewrite of the static `sidebar.blade.php` partial's `<ul class="side-nav">` block into the six approved sections, with no new data-driven menu system (the file is and stays hand-written HTML, consistent with the rest of the codebase).

**Tech Stack:** Laravel 11, Blade views, PHPUnit feature tests (existing convention — no `RefreshDatabase`, tests run against the app's configured `mysql`/`laravel` database and use model factories directly, same as `tests/Feature/ProfileTest.php` etc.).

## Global Constraints

- No new sidebar item introduces permission/role gating — none exists today on `routes/backend.php` and none is added here (that belongs to the separate, not-yet-planned Admin Management spec).
- The `feature` slug for the Coming Soon page must be resolved through a fixed whitelist array in the controller, never echoed directly from the route parameter — it becomes on-page `{{ }}` content.
- Sidebar route names, hrefs, and active-state (`Route::currentRouteNamed`) checks for all *existing* pages must be preserved exactly — only their grouping/section/label changes.
- Labels use the exact casing from the approved design table: "Dashboard", "User Management", "Social Feed", "Marketplace/Ad", "Event", "Interest Hub", "Courier", "CMS", "Subscription Management", "Subscriptions", "Subscription Plan", "Transaction", "Report Management", "Customer Support", "System Setting", "Credential Management", "Admin Management".

---

### Task 1: Coming Soon placeholder page

**Files:**
- Create: `app/Http/Controllers/Web/Backend/ComingSoonController.php`
- Create: `resources/views/backend/layouts/coming_soon/index.blade.php`
- Modify: `routes/backend.php` (add one route)
- Test: `tests/Feature/ComingSoonPageTest.php`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: route name `admin.coming-soon` (signature `route('admin.coming-soon', string $slug)`), accepting one of `marketplace-ad`, `event`, `interest-hub`, `courier`, `cms`, `admin-management`. Task 2's sidebar links depend on this route existing.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/ComingSoonPageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ComingSoonPageTest extends TestCase
{
    public function test_authenticated_user_sees_placeholder_for_known_feature(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.coming-soon', 'marketplace-ad'));

        $response->assertOk();
        $response->assertSee('Marketplace/Ad');
        $response->assertSee('Coming soon');
    }

    public function test_unknown_feature_slug_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.coming-soon', 'not-a-real-feature'));

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/ComingSoonPageTest.php`
Expected: FAIL — `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [admin.coming-soon] not defined.` (the route doesn't exist yet).

- [ ] **Step 3: Add the route**

In `routes/backend.php`, add this block directly after the `Dashboard` route group (after line 36, before the `SystemController` group):

```php

// Generic placeholder for sidebar entries whose feature isn't built yet.
Route::get('/coming-soon/{feature}', [\App\Http\Controllers\Web\Backend\ComingSoonController::class, 'index'])
    ->name('admin.coming-soon');
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Web/Backend/ComingSoonController.php`:

```php
<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;

class ComingSoonController extends Controller
{
    /**
     * Sidebar entries with no backing feature yet. Resolved through this
     * fixed whitelist (rather than echoing the route parameter directly)
     * since the slug becomes on-page content.
     */
    private const FEATURES = [
        'marketplace-ad'   => 'Marketplace/Ad',
        'event'            => 'Event',
        'interest-hub'     => 'Interest Hub',
        'courier'          => 'Courier',
        'cms'              => 'CMS',
        'admin-management' => 'Admin Management',
    ];

    public function index(string $feature)
    {
        abort_unless(array_key_exists($feature, self::FEATURES), 404);

        return view('backend.layouts.coming_soon.index', [
            'feature' => self::FEATURES[$feature],
        ]);
    }
}
```

- [ ] **Step 5: Create the view**

Create `resources/views/backend/layouts/coming_soon/index.blade.php`:

```blade
@extends('backend.master')

@section('page_title', $feature)

@section('content')

    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
        <i class="ti ti-tools fs-lg"></i>
        <div>
            <strong>{{ $feature }} — Coming soon.</strong> This section isn't available yet.
        </div>
    </div>

@endsection
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Feature/ComingSoonPageTest.php`
Expected: PASS (2 tests, 2 assertions or more).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Web/Backend/ComingSoonController.php resources/views/backend/layouts/coming_soon/index.blade.php routes/backend.php tests/Feature/ComingSoonPageTest.php
git commit -m "feat: Add generic Coming Soon placeholder page for backend sidebar"
```

---

### Task 2: Reorganize the sidebar

**Files:**
- Modify: `resources/views/backend/partial/sidebar.blade.php:81-296` (the `<ul class="side-nav">...</ul>` block)
- Test: `tests/Feature/AdminSidebarTest.php`

**Interfaces:**
- Consumes: route `admin.coming-soon` from Task 1 (must run after Task 1).
- Produces: nothing consumed by later tasks — this is the final task in this plan.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/AdminSidebarTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    public function test_sidebar_reflects_the_reorganized_sections(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        // Main — User Management moved here from Settings
        $response->assertSee(route('admin.user.lists'), false);

        // Content & Community — new coming-soon placeholders
        $response->assertSee(route('admin.coming-soon', 'marketplace-ad'), false);
        $response->assertSee(route('admin.coming-soon', 'event'), false);
        $response->assertSee(route('admin.coming-soon', 'interest-hub'), false);
        $response->assertSee(route('admin.coming-soon', 'courier'), false);
        $response->assertSee(route('admin.coming-soon', 'cms'), false);

        // Billing — Subscriptions + Subscription Plan grouped into one dropdown
        $response->assertSee('Subscription Management');

        // Support — Post Reports renamed + moved, Dynamic Pages moved from Settings
        $response->assertSee('Report Management');
        $response->assertSee(route('dynamic.pages'), false);

        // General Setting — renamed items + new Admin Management stub
        $response->assertSee('Credential Management');
        $response->assertSee(route('admin.coming-soon', 'admin-management'), false);

        // Dead theme-template links removed
        $response->assertDontSee('Demo 01');
        $response->assertDontSee('index.html', false);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/AdminSidebarTest.php`
Expected: FAIL — assertions for `Subscription Management`, `Report Management`, `Credential Management`, and the `admin.coming-soon` hrefs fail (current sidebar doesn't have them), and/or the `assertDontSee('Demo 01')` fails (it's still present).

- [ ] **Step 3: Replace the sidebar's `<ul class="side-nav">` block**

In `resources/views/backend/partial/sidebar.blade.php`, replace everything from the opening `<ul class="side-nav">` (line 81) through its closing `</ul>` (line 296) with:

```blade
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
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/AdminSidebarTest.php`
Expected: PASS.

- [ ] **Step 5: Run the full test suite for regressions**

Run: `php artisan test tests/Feature/ComingSoonPageTest.php tests/Feature/AdminSidebarTest.php tests/Unit/ConversationDetailResourceTest.php`
Expected: all PASS. (Don't run the full `php artisan test` suite — this repo has ~54 pre-existing unrelated failures from a missing `Mockery` dev dependency; scope the run to the tests this plan touches plus the earlier conversation-pills fix, to avoid noise.)

- [ ] **Step 6: Manual browser verification**

Use the `run` skill to start the app and log into the backend panel. Visually confirm:
- Section order top to bottom: Main, Content & Community, AI Operations, Billing, Support, General Setting.
- "Demo 01" is gone.
- Clicking each of Marketplace/Ad, Event, Interest hub, Courier, CMS, and Admin Management shows the Coming Soon page with the correct feature name.
- The "Subscription Management" and "Credential Management" dropdowns expand/collapse correctly.
- Existing pages (Dashboard, Social Feed, Workspaces, etc.) still load from their relocated sidebar links.

- [ ] **Step 7: Commit**

```bash
git add resources/views/backend/partial/sidebar.blade.php tests/Feature/AdminSidebarTest.php
git commit -m "feat: Reorganize admin sidebar into Main/Content/AI Ops/Billing/Support/General Setting"
```
