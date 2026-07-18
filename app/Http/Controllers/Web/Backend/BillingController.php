<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class BillingController extends Controller
{
    public function subscriptionsData(Request $request)
    {
        $query = UserSubscription::query()->with(['user', 'plan']);

        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addIndexColumn()

            ->addColumn('user_name', function ($sub) {
                return $sub->user->name ?? 'Unknown';
            })

            ->addColumn('plan_name', function ($sub) {
                return $sub->plan->name ?? 'Unknown';
            })

            ->addColumn('start', function ($sub) {
                return $sub->start_date ? $sub->start_date->format('d M Y') : '—';
            })

            ->addColumn('end', function ($sub) {
                return $sub->end_date ? $sub->end_date->format('d M Y') : '—';
            })

            ->addColumn('status_badge', function ($sub) {
                $map = [
                    'active'    => 'success',
                    'cancelled' => 'secondary',
                    'expired'   => 'danger',
                ];
                $color = $map[$sub->status] ?? 'secondary';

                return '<span class="badge bg-' . $color . '-subtle text-' . $color . '">' . ucfirst($sub->status) . '</span>';
            })

            ->rawColumns(['status_badge'])
            ->make(true);
    }

    public function subscriptions()
    {
        return view('backend.layouts.billing.subscriptions');
    }
}
