<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;

class SupportTicketController extends Controller
{
    public function data(Request $request)
    {
        $query = SupportTicket::query()->with('user');

        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addIndexColumn()

            ->addColumn('user_name', function ($ticket) {
                return $ticket->user->name ?? 'Unknown';
            })

            ->addColumn('created', function ($ticket) {
                return $ticket->created_at->format('d M Y');
            })

            ->addColumn('status_badge', function ($ticket) {
                $map = [
                    'open'        => 'danger',
                    'in_progress' => 'warning',
                    'resolved'    => 'success',
                    'closed'      => 'secondary',
                ];
                $color = $map[$ticket->status] ?? 'secondary';

                return '<span class="badge bg-' . $color . '-subtle text-' . $color . '">' . ucfirst(str_replace('_', ' ', $ticket->status)) . '</span>';
            })

            ->addColumn('action', function ($ticket) {
                return '
                <div class="d-flex justify-content-center gap-1">
                    <a href="' . route('admin.support-tickets.show', $ticket->id) . '" class="btn btn-default btn-icon btn-sm">
                        <i class="ti ti-eye fs-lg"></i>
                    </a>
                </div>
            ';
            })

            ->rawColumns(['status_badge', 'action'])
            ->make(true);
    }

    public function index()
    {
        return view('backend.layouts.support_tickets.index');
    }

    public function show(SupportTicket $supportTicket)
    {
        $supportTicket->load('user');

        return view('backend.layouts.support_tickets.show', ['ticket' => $supportTicket]);
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
}
