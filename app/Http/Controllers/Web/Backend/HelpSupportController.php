<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\HelpSupport;

class HelpSupportController extends Controller
{
    public function index()
    {
        $messages = HelpSupport::with('customer')->latest()->paginate(20);

        return view('backend.layouts.help_support.index', compact('messages'));
    }

    public function show(HelpSupport $helpSupport)
    {
        $helpSupport->load('customer');

        return view('backend.layouts.help_support.show', ['message' => $helpSupport]);
    }

    public function destroy(HelpSupport $helpSupport)
    {
        $helpSupport->delete();

        return redirect()->route('admin.help-support.index')->with('success', 'Message deleted successfully');
    }
}
