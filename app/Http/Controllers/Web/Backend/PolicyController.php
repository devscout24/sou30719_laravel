<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\Policy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PolicyController extends Controller
{
    public function edit()
    {
        $policy = Policy::firstOrCreate(['type' => 'disclaimers'], ['content' => '']);

        return view('backend.layouts.policies.edit', compact('policy'));
    }

    public function update(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first())->withInput();
        }

        $policy = Policy::firstOrCreate(['type' => 'disclaimers']);
        $policy->update(['content' => $request->content]);

        return redirect()->route('admin.policies.edit')->with('success', 'Disclaimers policy updated successfully');
    }
}
