<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::withCount('userSubscriptions')->orderBy('price')->get();

        return view('backend.layouts.subscription_plans.index', compact('plans'));
    }

    public function create()
    {
        return view('backend.layouts.subscription_plans.create');
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name'                     => 'required|string|max:255',
            'billing_cycle'            => 'required|in:monthly,yearly',
            'price'                    => 'required|numeric|min:0',
            'max_posts_per_day'        => 'nullable|integer|min:0',
            'max_matches_per_day'      => 'nullable|integer|min:0',
            'max_ai_requests_per_day'  => 'nullable|integer|min:0',
            'is_active'                => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first())->withInput();
        }

        SubscriptionPlan::create([
            'name'                    => $request->name,
            'slug'                    => $this->generateUniqueSlug($request->name, SubscriptionPlan::class),
            'billing_cycle'           => $request->billing_cycle,
            'price'                   => $request->price,
            'max_posts_per_day'       => $request->max_posts_per_day ?: null,
            'max_matches_per_day'     => $request->max_matches_per_day ?: null,
            'max_ai_requests_per_day' => $request->max_ai_requests_per_day ?: null,
            'is_active'               => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.plans.index')->with('success', 'Subscription plan created successfully');
    }

    public function edit(SubscriptionPlan $plan)
    {
        return view('backend.layouts.subscription_plans.edit', compact('plan'));
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $validation = Validator::make($request->all(), [
            'name'                     => 'required|string|max:255',
            'billing_cycle'            => 'required|in:monthly,yearly',
            'price'                    => 'required|numeric|min:0',
            'max_posts_per_day'        => 'nullable|integer|min:0',
            'max_matches_per_day'      => 'nullable|integer|min:0',
            'max_ai_requests_per_day'  => 'nullable|integer|min:0',
            'is_active'                => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first())->withInput();
        }

        $plan->update([
            'name'                    => $request->name,
            'billing_cycle'           => $request->billing_cycle,
            'price'                   => $request->price,
            'max_posts_per_day'       => $request->max_posts_per_day ?: null,
            'max_matches_per_day'     => $request->max_matches_per_day ?: null,
            'max_ai_requests_per_day' => $request->max_ai_requests_per_day ?: null,
            'is_active'               => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.plans.index')->with('success', 'Subscription plan updated successfully');
    }

    public function destroy(SubscriptionPlan $plan)
    {
        $plan->delete();

        return redirect()->route('admin.plans.index')->with('success', 'Subscription plan deleted successfully');
    }

    public function updateStatus(Request $request, SubscriptionPlan $plan)
    {
        $plan->update(['is_active' => $request->boolean('status')]);

        return response()->json(['success' => true]);
    }
}
