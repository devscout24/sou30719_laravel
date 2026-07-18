# Subscription & Payments Module Design

## Purpose
Complete the user-facing subscription/payment flow so the Subscription page (plan selection + "Upgrade plan") and the Payments settings page (search + paginated history + view detail) shown in the frontend mockups have working APIs. Admin already manages `SubscriptionPlan` records via the existing backend `SubscriptionPlanController` — that part is unchanged.

## Scope
Real Stripe integration is explicitly out of scope for this pass (no `stripe/stripe-php` dependency exists, no webhook handler exists, and the "Stripe key/secret" in admin settings are just stored credentials with no wired checkout flow). Subscribing to a plan is a direct, mock transaction: the `Payment` row is created already `paid`, no external gateway call.

## Endpoints
All under the existing `auth:api` middleware group in `routes/api.php`, in a new `App\Http\Controllers\API\SubscriptionController`.

### `GET /subscription-plans`
Active plans (`SubscriptionPlan::active()`), ordered by `price`. Each item flagged `is_current: bool` — true when it matches the authenticated user's current active subscription's `plan_id` (via `User::activeSubscription()`, which already exists).

### `POST /subscriptions/{plan}/subscribe`
Route-model-bound `SubscriptionPlan`.
- 422 if the plan is not active, or the user is already actively subscribed to this exact plan.
- Cancels any existing active `UserSubscription` (`->cancel()`, already exists on the model) — a user can only have one active plan.
- Creates a new `UserSubscription`: `start_date = now()`, `end_date = now()->addMonth()` or `->addYear()` per `billing_cycle`, `status = active`.
- If `plan.price > 0`: creates a `Payment` (`context: subscription`, `amount: plan.price`, `tax: 0`, `currency: usd`, `status: paid`, `payment_method: wallet`, `paid_at: now()`, `subscription_id` = the new subscription's id). Free plans create no payment row — nothing was charged.

### `GET /user-payments`
Same pagination convention as `FriendController` (`per_page`/`page`/`search` query params, `paginationParams()`/`paginationMeta()`-style helpers). `search` matches (case-insensitively) against the payment's formatted ID, its subscription plan's name, or its context label. Ordered by `paid_at` (falling back to `created_at`) descending.

### `GET /user-payments/{payment}`
Route-model-bound `Payment`. Returns 404 (via `$this->error([], 'Payment not found', 404)`) if the payment doesn't belong to the authenticated user — never leaks another user's payment by ID.

## Resource shape
One `PaymentResource` reused for both the list and the detail endpoint (no separate "detail" resource — the full shape is small enough that hiding fields in the list buys nothing):

```json
{
  "id": 42,
  "formatted_id": "#ID-000042",
  "plan_name": "Premium Subscription",
  "billing_cycle": "monthly",
  "amount": "59.99",
  "tax": "0.00",
  "total": "59.99",
  "currency": "usd",
  "status": "paid",
  "status_label": "Successful",
  "payment_method": "wallet",
  "invoice_url": null,
  "receipt_url": null,
  "paid_at": "2026-07-18T10:00:00.000000Z",
  "created_at": "2026-07-18T10:00:00.000000Z"
}
```

`SubscriptionPlanResource`:
```json
{
  "id": 3,
  "name": "Pro",
  "slug": "pro",
  "billing_cycle": "monthly",
  "price": "29.99",
  "formatted_price": "$29.99/Month",
  "max_posts_per_day": null,
  "max_matches_per_day": 15,
  "max_ai_requests_per_day": 100,
  "is_current": false
}
```
(`formatted_price` is `"Free"` when `price == 0`.)

## Postman collection
`postman_collection.json` (12,369 lines, root of repo) is regenerated programmatically, not hand-edited: `json_decode` the file, append a new `"Subscriptions & Payments"` folder to the top-level `item` array (following the existing `Chat` folder's structure — bearer `{{token}}` auth, `Content-Type: application/json` header, one `"Success"` example response per request), then `json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`. This flag combination was verified to round-trip the existing file byte-for-byte, so the rest of the collection is guaranteed untouched.

## Out of scope
- Real Stripe checkout/webhooks.
- Changes to admin plan management or the `subscription_plans` schema (the mockup's per-feature checklist — Feed Post/Matches/Event/etc. — isn't backed by columns today; not adding them here).
- A "cancel my subscription" user-facing endpoint (not requested).
