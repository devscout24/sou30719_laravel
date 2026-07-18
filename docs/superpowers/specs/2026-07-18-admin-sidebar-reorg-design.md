# Admin Sidebar Reorganization — Design Spec

Date: 2026-07-18
Status: Approved

## Problem

The backend admin sidebar (`resources/views/backend/partial/sidebar.blade.php`) has grown organically and no longer matches the navigation structure the product wants: items are grouped under the wrong sections (e.g. Dynamic Pages and User Management live under "Settings" instead of "Support"/"Main"), some existing pages need renaming, several planned-but-unbuilt feature areas (Marketplace/Ad, Event, Interest hub, Courier, CMS) have no sidebar entry point at all, and there's leftover dead-link cruft from the original theme template ("Demo 01").

## Scope

In scope:
- Rework `sidebar.blade.php` into six sections, in this order: **Main, Content & Community, AI Operations, Billing, Support, General Setting**.
- Reuse all existing routes; the only new route is a generic "Coming Soon" placeholder page.
- Remove the dead "Demo 01" theme-template links.

Out of scope (deferred to a separate spec):
- The actual **Admin Management** feature (admin accounts creating other admin accounts). This spec only reserves its place in the sidebar as a "Soon"-badged stub pointing at the new generic Coming Soon page, so the sidebar layout is complete now and doesn't need a second structural edit later.
- Any permission/role gating of sidebar items. None exists today (confirmed: no `@can`, `Gate::`, or role middleware anywhere in `routes/backend.php` or its controllers) and this reorg doesn't introduce any — that belongs with the Admin Management work.
- The pre-existing gap where any authenticated user (any role) can reach the backend panel — noted for awareness, not fixed here.

## Final sidebar structure

| Section | Item | Route name(s) | Notes |
|---|---|---|---|
| **Main** | Dashboard | `dashboard` | unchanged |
| | User Management (dropdown: Users List, Create User) | `admin.user.lists`, `admin.user.create` | moved from Settings |
| **Content & Community** | Social Feed | `admin.social-feed.index` | unchanged |
| | Workspaces | `admin.workspaces.index` | unchanged |
| | Feed Topics | `admin.feed-topics.index` | unchanged |
| | Posts | `admin.posts.index` | unchanged |
| | Marketplace/Ad | `admin.coming-soon` (slug `marketplace-ad`) | new placeholder |
| | Event | `admin.coming-soon` (slug `event`) | new placeholder |
| | Interest hub | `admin.coming-soon` (slug `interest-hub`) | new placeholder |
| | Courier | `admin.coming-soon` (slug `courier`) | new placeholder |
| | CMS | `admin.coming-soon` (slug `cms`) | new placeholder |
| **AI Operations** | LLM Agent Log (dropdown → LLM transaction & cost) | `admin.llm-agent-log.index`, `admin.llm-agent-log.transaction-cost` | unchanged, already matches target structure |
| **Billing** | Subscription Management (dropdown: Subscriptions, Subscription Plan) | `admin.billing.subscriptions`, `admin.plans.index` | newly grouped into a dropdown; previously two separate top-level items |
| | Transaction | `admin.transactions.index` | unchanged |
| **Support** | Dynamic Pages | `dynamic.pages` | moved from Settings |
| | Customer Support | `admin.support-tickets.index` | unchanged |
| | Report Management | `admin.post-reports.index` | renamed from "Post Reports"; moved from Content & Community |
| | Help & Support | `admin.help-support.index` | kept |
| | Disclaimers | `admin.policies.edit` | kept |
| **General Setting** | My Profile | `admin.user.profile` | unchanged |
| | System Setting | `system.settings` | label tweak ("Settings" → "Setting") |
| | Credential Management (dropdown: Mail / Stripe / Google Console) | `system.settings.credential` | renamed from "Credentials Settings" |
| | Admin Management | `admin.coming-soon` (slug `admin-management`) | **stub only** — placeholder until the Admin Management feature ships in a later spec |
| | Log Out | `logout` | unchanged |

Every item routed to the new Coming Soon page (the 5 Content & Community placeholders, plus Admin Management) carries the same `Soon` badge already used on the LLM Agent Log parent link, for visual consistency.

Also removed: the "Demo 01" collapse group under Main (two dead links to `index.html`, leftover from the original admin theme, not real application pages).

## Components

### 1. `sidebar.blade.php` (modified)

Stays a static, hand-written Blade partial — consistent with how it's built today (no data-driven menu array exists in this codebase, and introducing one is a larger architectural change not needed to satisfy this reorg). The six sections are reordered/relabeled per the table above, `Route::currentRouteNamed(...)` active-state checks carried over unchanged for every relocated item, and the Demo 01 block deleted.

### 2. Coming Soon page (new)

A single reusable placeholder used by all six not-yet-built sidebar entries, rather than six bespoke pages:

- **Route** (`routes/backend.php`, inside the existing `web`+`auth`+`lock.screen` group):
  ```php
  Route::get('coming-soon/{feature}', [ComingSoonController::class, 'index'])->name('admin.coming-soon');
  ```
- **Controller**: `App\Http\Controllers\Web\Backend\ComingSoonController@index($feature)`. Maps the `feature` route-slug to a display title via a small whitelist constant:
  ```php
  private const FEATURES = [
      'marketplace-ad'   => 'Marketplace/Ad',
      'event'            => 'Event',
      'interest-hub'     => 'Interest Hub',
      'courier'          => 'Courier',
      'cms'              => 'CMS',
      'admin-management' => 'Admin Management',
  ];
  ```
  An unrecognized slug results in a 404 (`abort(404)`), not a reflected/arbitrary title — the slug ultimately becomes on-page content, so it's resolved through a fixed whitelist rather than echoed directly.
- **View**: `resources/views/backend/layouts/coming_soon.blade.php`, extending the standard backend master layout, following the existing `ai_agent_log/index.blade.php` placeholder convention: a banner reading "**{Feature}** — Coming soon. This section isn't available yet."

## Data flow / error handling

No new data model, no persistence — this is pure routing/view work. The only failure path is `ComingSoonController` receiving a `feature` slug outside the whitelist, which 404s via Laravel's standard error page (same as any other invalid route parameter in this app).

## Testing

- **Feature test** (`tests/Feature/ComingSoonPageTest.php`): as an authenticated user, hitting `admin.coming-soon` with each whitelisted slug returns 200 and renders the expected feature title; an unknown slug returns 404.
- **Sidebar smoke check**: render the dashboard page as an authenticated user and assert a few relocated/renamed links are present with the right href — User Management now under Main, "Report Management" label present, "Subscription Management" dropdown present, Demo 01 links absent.
- **Manual verification**: load the admin panel in a browser (via the `run` skill) and visually confirm section order, dropdown behavior, and badges match the table above.

## Open questions

None — all ambiguities were resolved during the brainstorming discussion (see table above for each resolved decision).
