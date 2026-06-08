<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\DynamicPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DynamicPageController extends Controller
{
    // Index
    public function index()
    {
        $dynamicPages = DynamicPage::latest()->get();
        return view('backend.layouts.dynamic_pages.index', compact('dynamicPages'));
    }

    public function show(DynamicPage $page)
    {
        return view('backend.layouts.dynamic_pages.show', compact('page'));
    }

    public function edit(DynamicPage $page)
    {
        return view('backend.layouts.dynamic_pages.edit', compact('page'));
    }

    public function update(Request $request, DynamicPage $page)
    {
        $validate = Validator::make($request->all(), [
            'page_name' => 'required|string|max:255',
            'content'   => 'required',
        ]);

        if ($validate->fails()) {
            return redirect()
                ->route('dynamic.pages.edit', $page->id)
                ->withErrors($validate)
                ->withInput();
        }

        $page->update([
            'page_name' => $request->page_name,
            'content'   => $request->content,
        ]);

        return redirect()
            ->route('dynamic.pages')
            ->with('success', 'Page updated successfully!');
    }

    public function updateStatus(Request $request, DynamicPage $page)
    {
        $validate = Validator::make($request->all(), [
            'status' => 'required|boolean'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validate->errors()
            ], 400);
        }

        $page->update([
            'is_active' => $request->status
        ]);

        return response()->json([
            'success' => true
        ]);
    }
}
