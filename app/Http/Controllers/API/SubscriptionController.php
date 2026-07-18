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
