<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\Payment;
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

    public function paymentsData(Request $request)
    {
        $query = Payment::query()->with('user');

        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addIndexColumn()

            ->addColumn('user_name', function ($payment) {
                return $payment->user->name ?? 'Unknown';
            })

            ->addColumn('amount_display', function ($payment) {
                return number_format($payment->amount, 2) . ' ' . strtoupper($payment->currency);
            })

            ->addColumn('paid', function ($payment) {
                return $payment->paid_at ? $payment->paid_at->format('d M Y') : '—';
            })

            ->addColumn('status_badge', function ($payment) {
                $map = [
                    'paid'     => 'success',
                    'pending'  => 'warning',
                    'failed'   => 'danger',
                    'refunded' => 'secondary',
                ];
                $color = $map[$payment->status] ?? 'secondary';

                return '<span class="badge bg-' . $color . '-subtle text-' . $color . '">' . ucfirst($payment->status) . '</span>';
            })

            ->addColumn('receipt', function ($payment) {
                if (!$payment->receipt_url) {
                    return '—';
                }

                return '<a href="' . e($payment->receipt_url) . '" target="_blank" class="btn btn-default btn-icon btn-sm"><i class="ti ti-file-invoice fs-lg"></i></a>';
            })

            ->rawColumns(['status_badge', 'receipt'])
            ->make(true);
    }

    public function payments()
    {
        return view('backend.layouts.billing.payments');
    }
}
