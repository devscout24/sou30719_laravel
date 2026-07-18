# Subscription & Payments Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the frontend a working "subscribe to a plan" flow and a "payment history" API, matching the Subscription and Payments mockups, using a mock (non-Stripe) payment record.

**Architecture:** One new `App\Http\Controllers\API\SubscriptionController` with four endpoints (list plans, subscribe, list payments, show one payment), backed by two new `JsonResource` classes and two small helper methods added to the existing `Payment` model. No schema changes — `SubscriptionPlan`, `UserSubscription`, `Payment` already have everything needed. Registered under the existing `auth:api` route group in `routes/api.php`. The Postman collection is updated by a one-off PHP script (decode → append a new folder → re-encode with flags verified to round-trip the file byte-for-byte), not by hand-editing the 12k-line JSON.

**Tech Stack:** Laravel 11, Eloquent, `Auth::guard('api')` (JWT), existing `ApiResponse` trait for `$this->success()/$this->error()`.

## Global Constraints
- No Stripe SDK/webhooks — subscribing creates a `Payment` row already `status: paid`. Free plans (`price == 0`) create no `Payment` row.
- Follow existing controller conventions: `Auth::guard('api')->id()`/`->user()`, `$this->success()/$this->error()` from `ApiResponse`, inline `per_page`/`page`/`search` query parsing exactly like `FriendController::search()` (no new shared trait — this codebase already duplicates this 3-line pattern per controller).
- Route model binding by parameter name (`{plan}` → `SubscriptionPlan $plan`, `{payment}` → `Payment $payment`), same as `ChatController::conversation(Conversation $conversation)`.
- No automated test suite covers any existing API controller in this repo (`tests/Feature` is only Jetstream/Breeze scaffolding + two admin view tests) — verification here is a live HTTP smoke test against the local Herd site (`https://sou30719.test`), matching the only verification convention this codebase actually has for this layer.

---

### Task 1: Payment model helpers

**Files:**
- Modify: `app/Models/Payment.php`

**Interfaces:**
- Produces: `Payment::formattedId(): string`, `Payment::planName(): ?string` — consumed by `PaymentResource` (Task 3) and `SubscriptionController::payments()` (Task 4).

- [ ] **Step 1: Add the two helper methods**

In `app/Models/Payment.php`, add after the existing `methodLabel()` method (before `getTotalAttribute()`):

```php
    public function formattedId(): string
    {
        return '#ID-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function planName(): ?string
    {
        return $this->subscription?->plan?->name;
    }
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/Models/Payment.php`
Expected: `No syntax errors detected`

---

### Task 2: `SubscriptionPlanResource` and `PaymentResource`

**Files:**
- Create: `app/Http/Resources/SubscriptionPlanResource.php`
- Create: `app/Http/Resources/PaymentResource.php`

**Interfaces:**
- Consumes: `SubscriptionPlan` (`id`, `name`, `slug`, `billing_cycle`, `price`, `max_posts_per_day`, `max_matches_per_day`, `max_ai_requests_per_day`) with an `is_current` attribute the controller sets on the model instance before wrapping it (`$plan->is_current = ...`). `Payment` (`id`, `amount`, `tax`, `total` [existing accessor], `currency`, `status`, `payment_method`, `invoice_url`, `receipt_url`, `paid_at`, `created_at`, `statusLabel()`, `formattedId()`, `planName()`, `subscription.plan.billing_cycle`).
- Produces: `SubscriptionPlanResource`, `PaymentResource` — consumed by `SubscriptionController` (Task 4).

- [ ] **Step 1: Create `SubscriptionPlanResource`**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'slug'                    => $this->slug,
            'billing_cycle'           => $this->billing_cycle,
            'price'                   => $this->price,
            'formatted_price'         => $this->price > 0
                ? '$' . number_format($this->price, 2) . '/' . ($this->billing_cycle === 'yearly' ? 'Year' : 'Month')
                : 'Free',
            'max_posts_per_day'       => $this->max_posts_per_day,
            'max_matches_per_day'     => $this->max_matches_per_day,
            'max_ai_requests_per_day' => $this->max_ai_requests_per_day,
            'is_current'              => (bool) $this->is_current,
        ];
    }
}
```

- [ ] **Step 2: Create `PaymentResource`**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'formatted_id'   => $this->formattedId(),
            'plan_name'      => $this->planName(),
            'billing_cycle'  => $this->subscription?->plan?->billing_cycle,
            'amount'         => $this->amount,
            'tax'            => $this->tax,
            'total'          => $this->total,
            'currency'       => $this->currency,
            'status'         => $this->status,
            'status_label'   => $this->statusLabel(),
            'payment_method' => $this->payment_method,
            'invoice_url'    => $this->invoice_url,
            'receipt_url'    => $this->receipt_url,
            'paid_at'        => $this->paid_at?->toISOString(),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 3: Verify syntax**

Run: `php -l app/Http/Resources/SubscriptionPlanResource.php` and `php -l app/Http/Resources/PaymentResource.php`
Expected: `No syntax errors detected` for both.

---

### Task 3: `SubscriptionController`

**Files:**
- Create: `app/Http/Controllers/API/SubscriptionController.php`

**Interfaces:**
- Consumes: `App\Models\SubscriptionPlan` (`active()` scope, `is_active`, `billing_cycle`, `price`), `App\Models\UserSubscription` (`create()`, `->cancel()`, `->payments()`), `App\Models\Payment` (`create()`), `App\Models\User::activeSubscription` (existing relation), `App\Http\Resources\SubscriptionPlanResource`, `App\Http\Resources\PaymentResource` (Task 2), `App\Traits\ApiResponse` (`$this->success()/$this->error()`).
- Produces: `SubscriptionController::plans()`, `::subscribe(SubscriptionPlan $plan)`, `::payments(Request $request)`, `::payment(Payment $payment)` — consumed by `routes/api.php` (Task 4).

- [ ] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    use ApiResponse;

    /**
     * Active plans available to purchase, flagged with the user's current plan.
     */
    public function plans()
    {
        $user = Auth::guard('api')->user();
        $currentPlanId = $user->activeSubscription?->plan_id;

        $plans = SubscriptionPlan::active()->orderBy('price')->get();
        $plans->each(fn (SubscriptionPlan $plan) => $plan->is_current = $plan->id === $currentPlanId);

        return $this->success(SubscriptionPlanResource::collection($plans), 'Subscription plans fetched successfully');
    }

    /**
     * Subscribe the authenticated user to a plan, replacing any existing active one.
     * Records a paid Payment immediately (mock — no external gateway call).
     */
    public function subscribe(SubscriptionPlan $plan)
    {
        $user = Auth::guard('api')->user();

        if (!$plan->is_active) {
            return $this->error([], 'This plan is not available', 422);
        }

        $current = $user->activeSubscription;

        if ($current && $current->plan_id === $plan->id) {
            return $this->error([], 'You are already subscribed to this plan', 422);
        }

        if ($current) {
            $current->cancel();
        }

        $subscription = DB::transaction(function () use ($user, $plan) {
            $subscription = UserSubscription::create([
                'user_id'    => $user->id,
                'plan_id'    => $plan->id,
                'start_date' => now(),
                'end_date'   => $plan->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                'status'     => 'active',
            ]);

            if ($plan->price > 0) {
                Payment::create([
                    'user_id'         => $user->id,
                    'subscription_id' => $subscription->id,
                    'context'         => 'subscription',
                    'amount'          => $plan->price,
                    'tax'             => 0,
                    'currency'        => 'usd',
                    'status'          => 'paid',
                    'payment_method'  => 'wallet',
                    'paid_at'         => now(),
                ]);
            }

            return $subscription;
        });

        $subscription->load('plan');
        $payment = $subscription->payments()->latest()->first();

        return $this->success([
            'subscription' => [
                'id'            => $subscription->id,
                'plan_id'       => $subscription->plan_id,
                'plan_name'     => $subscription->plan->name,
                'billing_cycle' => $subscription->plan->billing_cycle,
                'status'        => $subscription->status,
                'start_date'    => $subscription->start_date?->toISOString(),
                'end_date'      => $subscription->end_date?->toISOString(),
            ],
            'payment' => $payment ? new PaymentResource($payment) : null,
        ], 'Subscribed successfully', 201);
    }

    /**
     * Paginated, searchable payment history for the authenticated user.
     */
    public function payments(Request $request)
    {
        $userId = Auth::guard('api')->id();

        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $page    = max((int) $request->query('page', 1), 1);
        $search  = trim((string) $request->query('search', ''));

        $query = Payment::where('user_id', $userId)->with('subscription.plan');

        if ($search !== '') {
            $numericId = ltrim(preg_replace('/\D/', '', $search), '0');

            $query->where(function ($q) use ($search, $numericId) {
                if ($numericId !== '') {
                    $q->orWhere('id', $numericId);
                }

                $q->orWhereHas('subscription.plan', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $payments = $query
            ->orderByRaw('COALESCE(paid_at, created_at) DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'payments'   => PaymentResource::collection($payments->items()),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page'     => $payments->perPage(),
                'total'        => $payments->total(),
                'last_page'    => $payments->lastPage(),
            ],
        ], 'Payment history fetched successfully');
    }

    /**
     * A single payment's detail (the "view" icon), scoped to the authenticated user.
     */
    public function payment(Payment $payment)
    {
        if ($payment->user_id !== Auth::guard('api')->id()) {
            return $this->error([], 'Payment not found', 404);
        }

        $payment->load('subscription.plan');

        return $this->success(new PaymentResource($payment), 'Payment fetched successfully');
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/Http/Controllers/API/SubscriptionController.php`
Expected: `No syntax errors detected`

---

### Task 4: Register routes and verify with a live HTTP smoke test

**Files:**
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `SubscriptionController` (Task 3).
- Produces: nothing further downstream — Task 5 (Postman) documents these routes but doesn't depend on route internals beyond the paths/methods below.

- [ ] **Step 1: Add the import**

In `routes/api.php`, add to the `use` block at the top:

```php
use App\Http\Controllers\API\SubscriptionController;
```

- [ ] **Step 2: Add the route group**

Inside the `Route::middleware('auth:api')->group(function () { ... })` block in `routes/api.php`, add after the `Chat` section (after the `Route::get('/chat/friends', ...)` line, before `// ── Admin: AI-generated posts ──`):

```php
    // ── Subscriptions & Payments ──────────────────────────────────────────────
    Route::controller(SubscriptionController::class)->group(function () {
        Route::get('/subscription-plans', 'plans');
        Route::post('/subscriptions/{plan}/subscribe', 'subscribe');
        Route::get('/user-payments', 'payments');
        Route::get('/user-payments/{payment}', 'payment');
    });
```

- [ ] **Step 3: Verify the routes are registered**

Run: `php artisan route:list --path=subscription` and `php artisan route:list --path=user-payments`
Expected: 4 rows total — `GET subscription-plans`, `POST subscriptions/{plan}/subscribe`, `GET user-payments`, `GET user-payments/{payment}` — all with `SubscriptionController` as the action and `auth:api` in the middleware column.

- [ ] **Step 4: Live smoke test against the local Herd site**

The app is served locally at `https://sou30719.test` (Laravel Herd). There's no automated test suite covering API controllers in this repo (verified: `tests/Feature` is only Jetstream/Breeze scaffolding), so verification here is a real HTTP run through the full stack — routing, middleware, controller, resource — using the seeded `user@user.com` account (from `UserSeeder`/`ChatSeeder`).

Write this script (it mints a JWT directly via `JWTAuth::fromUser()` rather than going through `/user-signin`, because that seeded user's `email_verified_at` is null and signin blocks unverified emails — orthogonal to this feature):

Create a scratch file (adjust the path to your own scratch/temp directory) `verify_subscriptions.php`:

```php
<?php

require 'C:/Users/Max/Desktop/Projects/s/sou30719/vendor/autoload.php';
$app = require 'C:/Users/Max/Desktop/Projects/s/sou30719/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'user@user.com')->first();
$token = Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

$plan = App\Models\SubscriptionPlan::firstOrCreate(
    ['slug' => 'verify-plus'],
    [
        'name' => 'Verify Plus', 'billing_cycle' => 'monthly', 'price' => 9.99,
        'max_posts_per_day' => 10, 'max_matches_per_day' => 10, 'max_ai_requests_per_day' => 30,
        'is_active' => true,
    ]
);

file_put_contents(__DIR__ . '/token.txt', $token);
echo "user_id={$user->id} plan_id={$plan->id} token_written\n";
```

- [ ] **Step 5: Run it and mint the token/plan**

Run: `php verify_subscriptions.php` (from the scratch directory)
Expected: `user_id=<N> plan_id=<M> token_written` with no errors.

- [ ] **Step 6: Hit `GET /subscription-plans`**

Run (replace `<TOKEN>` with the contents of `token.txt`):
```bash
curl -sk https://sou30719.test/api/subscription-plans -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"
```
Expected: `"status":true`, a JSON array under `data` including the `Verify Plus` plan with `"formatted_price":"$9.99/Month"` and `"is_current":false`.

- [ ] **Step 7: Hit `POST /subscriptions/{plan}/subscribe`**

Run (`<PLAN_ID>` = the `plan_id` printed in Step 5):
```bash
curl -sk -X POST https://sou30719.test/api/subscriptions/<PLAN_ID>/subscribe -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"
```
Expected: HTTP 201, `"status":true`, `data.subscription.plan_name == "Verify Plus"`, `data.payment` non-null with `"amount":"9.99"` and `"status":"paid"`.

- [ ] **Step 8: Re-run the same subscribe call**

Run the identical curl from Step 7 again.
Expected: `"status":false`, message `"You are already subscribed to this plan"`, HTTP 422 — confirms the duplicate-subscribe guard works.

- [ ] **Step 9: Hit `GET /user-payments`**

Run:
```bash
curl -sk "https://sou30719.test/api/user-payments?search=Verify" -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"
```
Expected: `"status":true`, `data.payments` contains exactly the payment from Step 7 (`plan_name: "Verify Plus"`), `data.pagination.total >= 1`.

- [ ] **Step 10: Hit `GET /user-payments/{payment}`**

Run (`<PAYMENT_ID>` = the `data.payment.id` from Step 7's response):
```bash
curl -sk https://sou30719.test/api/user-payments/<PAYMENT_ID> -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"
```
Expected: `"status":true`, `data.id == <PAYMENT_ID>`, `data.formatted_id` matches `#ID-0000NN` zero-padded to 6 digits.

- [ ] **Step 11: Confirm the 404 ownership guard**

Run the same request as Step 10 but with a payment ID that belongs to a different user (any `id` from `payments` table not owned by `user@user.com` — check via the tinker script from the ChatSeeder plan if needed, or just use `1` if that's the admin's).
Expected: `"status":false`, `"message":"Payment not found"`, HTTP 404.

---

### Task 5: Update `postman_collection.json`

**Files:**
- Modify: `postman_collection.json`

**Interfaces:**
- Consumes: the 4 routes from Task 4 (exact paths/methods).
- Produces: nothing further downstream — last task.

- [ ] **Step 1: Confirm the round-trip flags (safety check)**

This was already verified during design (byte-identical round trip), but re-confirm right before writing, since this is a 656KB file with no backup mechanism beyond git. Create `roundtrip_check.php` in your scratch directory:

```php
<?php
$raw = file_get_contents('C:/Users/Max/Desktop/Projects/s/sou30719/postman_collection.json');
$data = json_decode($raw, true);
$out = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
echo ($raw === $out) ? "IDENTICAL\n" : "DIFFERS — STOP, do not proceed\n";
```

Run: `php roundtrip_check.php`
Expected: `IDENTICAL`

- [ ] **Step 2: Write the update script**

Create `add_subscriptions_to_postman.php` in your scratch directory:

```php
<?php

$path = 'C:/Users/Max/Desktop/Projects/s/sou30719/postman_collection.json';
$data = json_decode(file_get_contents($path), true);

function bearerAuth(): array
{
    return [
        'type'   => 'bearer',
        'bearer' => [
            ['key' => 'token', 'value' => '{{token}}', 'type' => 'string'],
        ],
    ];
}

function jsonHeader(): array
{
    return [['key' => 'Content-Type', 'value' => 'application/json']];
}

function successResponse(string $name, array $request, int $code, string $status, array $body): array
{
    return [
        'name'                => $name,
        'originalRequest'     => $request,
        'status'              => $status,
        'code'                => $code,
        '_postman_previewlanguage' => 'json',
        'header'              => jsonHeader(),
        'cookie'              => [],
        'body'                => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];
}

$getPlans = [
    'auth'        => bearerAuth(),
    'method'      => 'GET',
    'header'      => jsonHeader(),
    'url'         => [
        'raw'  => '{{base_url}}/subscription-plans',
        'host' => ['{{base_url}}'],
        'path' => ['subscription-plans'],
    ],
    'description' => 'Active subscription plans available to purchase, ordered by price. Each plan is flagged is_current against the authenticated user\'s active subscription.',
];

$subscribe = [
    'auth'        => bearerAuth(),
    'method'      => 'POST',
    'header'      => jsonHeader(),
    'url'         => [
        'raw'  => '{{base_url}}/subscriptions/{{plan_id}}/subscribe',
        'host' => ['{{base_url}}'],
        'path' => ['subscriptions', '{{plan_id}}', 'subscribe'],
    ],
    'description' => 'Subscribe the authenticated user to a plan (mock payment, no gateway call). Cancels any existing active subscription first. Free plans (price 0) create no Payment row.',
];

$listPayments = [
    'auth'        => bearerAuth(),
    'method'      => 'GET',
    'header'      => jsonHeader(),
    'url'         => [
        'raw'   => '{{base_url}}/user-payments?search={{search}}&per_page={{per_page}}',
        'host'  => ['{{base_url}}'],
        'path'  => ['user-payments'],
        'query' => [
            ['key' => 'search', 'value' => '{{search}}'],
            ['key' => 'per_page', 'value' => '{{per_page}}'],
        ],
    ],
    'description' => 'Paginated payment history for the authenticated user. search matches payment ID or subscription plan name.',
];

$showPayment = [
    'auth'        => bearerAuth(),
    'method'      => 'GET',
    'header'      => jsonHeader(),
    'url'         => [
        'raw'  => '{{base_url}}/user-payments/{{payment_id}}',
        'host' => ['{{base_url}}'],
        'path' => ['user-payments', '{{payment_id}}'],
    ],
    'description' => 'A single payment\'s full detail (the "view" icon on the Payments page). 404s if the payment does not belong to the authenticated user.',
];

$folder = [
    'name' => 'Subscriptions & Payments',
    'item' => [
        [
            'name'     => 'GET Subscription Plans',
            'request'  => $getPlans,
            'response' => [
                successResponse('Success', $getPlans, 200, 'OK', [
                    'status'  => true,
                    'message' => 'Subscription plans fetched successfully',
                    'data'    => [
                        [
                            'id' => 1, 'name' => 'Starter', 'slug' => 'starter',
                            'billing_cycle' => 'monthly', 'price' => '0.00',
                            'formatted_price' => 'Free', 'max_posts_per_day' => 5,
                            'max_matches_per_day' => 5, 'max_ai_requests_per_day' => 10,
                            'is_current' => true,
                        ],
                        [
                            'id' => 2, 'name' => 'Plus', 'slug' => 'plus',
                            'billing_cycle' => 'monthly', 'price' => '9.99',
                            'formatted_price' => '$9.99/Month', 'max_posts_per_day' => 10,
                            'max_matches_per_day' => 10, 'max_ai_requests_per_day' => 30,
                            'is_current' => false,
                        ],
                    ],
                    'code' => 200,
                ]),
            ],
        ],
        [
            'name'     => 'POST Subscribe to Plan',
            'request'  => $subscribe,
            'response' => [
                successResponse('Success', $subscribe, 201, 'Created', [
                    'status'  => true,
                    'message' => 'Subscribed successfully',
                    'data'    => [
                        'subscription' => [
                            'id' => 12, 'plan_id' => 2, 'plan_name' => 'Plus',
                            'billing_cycle' => 'monthly', 'status' => 'active',
                            'start_date' => '2026-07-18T10:00:00.000000Z',
                            'end_date' => '2026-08-18T10:00:00.000000Z',
                        ],
                        'payment' => [
                            'id' => 35, 'formatted_id' => '#ID-000035', 'plan_name' => 'Plus',
                            'billing_cycle' => 'monthly', 'amount' => '9.99', 'tax' => '0.00',
                            'total' => '9.99', 'currency' => 'usd', 'status' => 'paid',
                            'status_label' => 'Successful', 'payment_method' => 'wallet',
                            'invoice_url' => null, 'receipt_url' => null,
                            'paid_at' => '2026-07-18T10:00:00.000000Z',
                            'created_at' => '2026-07-18T10:00:00.000000Z',
                        ],
                    ],
                    'code' => 201,
                ]),
            ],
        ],
        [
            'name'     => 'GET User Payments',
            'request'  => $listPayments,
            'response' => [
                successResponse('Success', $listPayments, 200, 'OK', [
                    'status'  => true,
                    'message' => 'Payment history fetched successfully',
                    'data'    => [
                        'payments' => [
                            [
                                'id' => 35, 'formatted_id' => '#ID-000035', 'plan_name' => 'Premium',
                                'billing_cycle' => 'monthly', 'amount' => '59.99', 'tax' => '0.00',
                                'total' => '59.99', 'currency' => 'usd', 'status' => 'paid',
                                'status_label' => 'Successful', 'payment_method' => 'wallet',
                                'invoice_url' => null, 'receipt_url' => null,
                                'paid_at' => '2026-07-18T10:00:00.000000Z',
                                'created_at' => '2026-07-18T10:00:00.000000Z',
                            ],
                        ],
                        'pagination' => [
                            'current_page' => 1, 'per_page' => 15, 'total' => 1, 'last_page' => 1,
                        ],
                    ],
                    'code' => 200,
                ]),
            ],
        ],
        [
            'name'     => 'GET User Payment Detail',
            'request'  => $showPayment,
            'response' => [
                successResponse('Success', $showPayment, 200, 'OK', [
                    'status'  => true,
                    'message' => 'Payment fetched successfully',
                    'data'    => [
                        'id' => 35, 'formatted_id' => '#ID-000035', 'plan_name' => 'Premium',
                        'billing_cycle' => 'monthly', 'amount' => '59.99', 'tax' => '0.00',
                        'total' => '59.99', 'currency' => 'usd', 'status' => 'paid',
                        'status_label' => 'Successful', 'payment_method' => 'wallet',
                        'invoice_url' => null, 'receipt_url' => null,
                        'paid_at' => '2026-07-18T10:00:00.000000Z',
                        'created_at' => '2026-07-18T10:00:00.000000Z',
                    ],
                    'code' => 200,
                ]),
            ],
        ],
    ],
];

$data['item'][] = $folder;

file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

echo "Added 'Subscriptions & Payments' folder with " . count($folder['item']) . " requests.\n";
```

- [ ] **Step 3: Run it**

Run: `php add_subscriptions_to_postman.php`
Expected: `Added 'Subscriptions & Payments' folder with 4 requests.`

- [ ] **Step 4: Verify the collection is still valid JSON and the rest is untouched**

Run (PowerShell):
```powershell
$json = Get-Content -Raw postman_collection.json | ConvertFrom-Json
($json.item | Select-Object -Last 1).name
$json.item.Count
```
Expected: last line prints `Subscriptions & Payments`; the count is one more than before this task ran. Also spot-check an unrelated earlier section (e.g. `($json.item | Where-Object name -eq 'Chat').item.Count`) still shows its original request count (4).
