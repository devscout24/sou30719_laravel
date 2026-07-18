<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'daily');

        [$from, $to] = $this->resolvePeriodRange($period, $request->get('from'), $request->get('to'));

        $totalTickets    = SupportTicket::count();
        $pendingTickets  = SupportTicket::where('status', 'open')->count();
        $ongoingTickets  = SupportTicket::where('status', 'in_progress')->count();
        $resolvedTickets = SupportTicket::where('status', 'resolved')->count();
        $newTickets      = SupportTicket::whereBetween('created_at', [$from, $to])->count();

        $tickets = $this->filteredQuery($request)
            ->with('user')
            ->latest()
            ->paginate(6)
            ->withQueryString();

        $types = SupportTicket::TYPES;

        return view('backend.layouts.support_tickets.index', compact(
            'tickets',
            'totalTickets',
            'pendingTickets',
            'ongoingTickets',
            'resolvedTickets',
            'newTickets',
            'period',
            'types'
        ));
    }

    private function filteredQuery(Request $request)
    {
        $query = SupportTicket::query();

        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        if ($request->type && $request->type != 'All') {
            $query->where('type', $request->type);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
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

    public function show(Request $request, SupportTicket $supportTicket)
    {
        $supportTicket->load(['user', 'replies.user']);

        $recentTickets = SupportTicket::where('user_id', $supportTicket->user_id)
            ->where('id', '!=', $supportTicket->id)
            ->when($request->filled('recent_search'), function ($q) use ($request) {
                $search = $request->get('recent_search');
                $q->where(function ($qq) use ($search) {
                    $qq->where('subject', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->when($request->recent_status && $request->recent_status != 'All', function ($q) use ($request) {
                $q->where('status', $request->recent_status);
            })
            ->with('user')
            ->latest()
            ->paginate(3, ['*'], 'recent_page')
            ->withQueryString();

        return view('backend.layouts.support_tickets.show', [
            'ticket'        => $supportTicket,
            'recentTickets' => $recentTickets,
        ]);
    }

    public function updateStatus(Request $request, SupportTicket $supportTicket)
    {
        $validation = Validator::make($request->all(), [
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first());
        }

        $supportTicket->update(['status' => $request->status]);

        return redirect()->route('admin.support-tickets.show', $supportTicket)->with('success', 'Ticket status updated successfully');
    }

    public function storeReply(Request $request, SupportTicket $supportTicket)
    {
        $validation = Validator::make($request->all(), [
            'message' => 'required|string|max:5000',
        ]);

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first());
        }

        $supportTicket->replies()->create([
            'user_id'  => auth()->id(),
            'is_admin' => true,
            'message'  => $request->message,
        ]);

        return redirect()->route('admin.support-tickets.show', $supportTicket)->with('success', 'Reply sent successfully');
    }

    public function export(Request $request)
    {
        $query = $this->filteredQuery($request)->with('user');

        $filename = 'tickets-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'User', 'Type', 'Subject', 'Status', 'Posted']);

            $query->orderBy('id')->chunk(200, function ($tickets) use ($handle) {
                foreach ($tickets as $ticket) {
                    fputcsv($handle, [
                        $ticket->id,
                        $ticket->user->name ?? 'Unknown',
                        $ticket->typeLabel(),
                        $ticket->subject,
                        ucfirst(str_replace('_', ' ', $ticket->status)),
                        optional($ticket->created_at)->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
