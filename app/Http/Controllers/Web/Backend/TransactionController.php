<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'daily');

        [$from, $to] = $this->resolvePeriodRange($period, $request->get('from'), $request->get('to'));

        $totalTransactions   = Payment::count();
        $totalTransactionsSum = Payment::sum('amount');
        $pendingTransactions  = Payment::where('status', 'pending')->count();
        $failedTransactions   = Payment::where('status', 'failed')->count();
        $completeTransactions = Payment::where('status', 'paid')->count();

        $transactions = $this->filteredQuery($request)
            ->with(['user', 'subscription.plan'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $contexts = Payment::CONTEXTS;

        return view('backend.layouts.transactions.index', compact(
            'transactions',
            'totalTransactions',
            'totalTransactionsSum',
            'pendingTransactions',
            'failedTransactions',
            'completeTransactions',
            'period',
            'contexts'
        ));
    }

    private function filteredQuery(Request $request)
    {
        $query = Payment::query();

        if ($request->context && $request->context != 'All') {
            $query->where('context', $request->context);
        }

        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->whereHas('user', function ($uq) use ($search) {
                $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', explode(',', $request->get('ids')));
        }

        return $query;
    }

    private function resolvePeriodRange(string $period, $from = null, $to = null): array
    {
        $now = Carbon::now();

        return match ($period) {
            'weekly'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'yearly'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom'  => [
                $from ? Carbon::parse($from)->startOfDay() : $now->copy()->startOfMonth(),
                $to ? Carbon::parse($to)->endOfDay() : $now->copy()->endOfDay(),
            ],
            default   => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    public function show(Request $request, Payment $transaction)
    {
        $transaction->load(['user', 'subscription.plan']);

        $recentTransactions = Payment::where('user_id', $transaction->user_id)
            ->where('id', '!=', $transaction->id)
            ->when($request->recent_context && $request->recent_context != 'All', function ($q) use ($request) {
                $q->where('context', $request->recent_context);
            })
            ->when($request->filled('recent_search'), function ($q) use ($request) {
                $q->where('id', 'like', '%' . $request->get('recent_search') . '%');
            })
            ->with(['user', 'subscription.plan'])
            ->latest()
            ->paginate(5, ['*'], 'recent_page')
            ->withQueryString();

        return view('backend.layouts.transactions.show', compact('transaction', 'recentTransactions'));
    }

    public function invoice(Payment $transaction)
    {
        $transaction->load(['user', 'subscription.plan']);
        $company = CompanySetting::first();

        return view('backend.layouts.transactions.invoice', compact('transaction', 'company'));
    }

    public function downloadInvoice(Payment $transaction)
    {
        $transaction->load(['user', 'subscription.plan']);
        $company = CompanySetting::first();

        $pdf = Pdf::loadView('backend.layouts.transactions.invoice-pdf', compact('transaction', 'company'));

        return $pdf->download('invoice-' . $transaction->id . '.pdf');
    }

    public function export(Request $request)
    {
        $query = $this->filteredQuery($request)->with('user');

        $filename = 'transactions-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Date', 'User', 'Context', 'Amount', 'Tax', 'Status']);

            $query->orderBy('id')->chunk(200, function ($transactions) use ($handle) {
                foreach ($transactions as $transaction) {
                    fputcsv($handle, [
                        $transaction->id,
                        optional($transaction->created_at)->format('Y-m-d H:i'),
                        $transaction->user->name ?? 'Unknown',
                        $transaction->contextLabel(),
                        $transaction->amount,
                        $transaction->tax,
                        $transaction->statusLabel(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
