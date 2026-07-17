<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\UserFeedTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedTopicController extends Controller
{
    protected function baseQuery()
    {
        return UserFeedTopic::whereNull('user_id');
    }

    public function index()
    {
        $topics = $this->baseQuery()->orderBy('sort_order')->orderBy('name')->get();

        return view('backend.layouts.feed_topics.index', compact('topics'));
    }

    public function create()
    {
        return view('backend.layouts.feed_topics.create');
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'icon'         => 'nullable|string|max:100',
            'tag_keywords' => 'nullable|string',
            'sort_order'   => 'nullable|integer|min:0',
            'is_active'    => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first())->withInput();
        }

        $this->baseQuery()->create([
            'name'         => $request->name,
            'slug'         => $this->generateUniqueSlug($request->name, UserFeedTopic::class),
            'icon'         => $request->icon,
            'tag_keywords' => $this->parseKeywords($request->tag_keywords),
            'sort_order'   => $request->sort_order ?? 0,
            'is_fixed'     => true,
            'is_active'    => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.feed-topics.index')->with('success', 'Feed topic created successfully');
    }

    public function edit(UserFeedTopic $feedTopic)
    {
        abort_unless(is_null($feedTopic->user_id), 404);

        return view('backend.layouts.feed_topics.edit', ['topic' => $feedTopic]);
    }

    public function update(Request $request, UserFeedTopic $feedTopic)
    {
        abort_unless(is_null($feedTopic->user_id), 404);

        $validation = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'icon'         => 'nullable|string|max:100',
            'tag_keywords' => 'nullable|string',
            'sort_order'   => 'nullable|integer|min:0',
            'is_active'    => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return back()->with('error', $validation->errors()->first())->withInput();
        }

        $feedTopic->update([
            'name'         => $request->name,
            'icon'         => $request->icon,
            'tag_keywords' => $this->parseKeywords($request->tag_keywords),
            'sort_order'   => $request->sort_order ?? 0,
            'is_active'    => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.feed-topics.index')->with('success', 'Feed topic updated successfully');
    }

    public function destroy(UserFeedTopic $feedTopic)
    {
        abort_unless(is_null($feedTopic->user_id), 404);

        $feedTopic->delete();

        return redirect()->route('admin.feed-topics.index')->with('success', 'Feed topic deleted successfully');
    }

    public function updateStatus(Request $request, UserFeedTopic $feedTopic)
    {
        abort_unless(is_null($feedTopic->user_id), 404);

        $feedTopic->update(['is_active' => $request->boolean('status')]);

        return response()->json(['success' => true]);
    }

    private function parseKeywords(?string $raw): ?array
    {
        if (blank($raw)) {
            return null;
        }

        $keywords = array_filter(array_map('trim', explode(',', $raw)));

        return empty($keywords) ? null : array_values($keywords);
    }
}
