<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagFollowController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['tag' => ['required', 'string', 'max:40']]);
        $tag = Tag::where('name', Tag::canonicalName($data['tag']))->firstOrFail();
        $request->user()->followedTags()->syncWithoutDetaching([$tag->id]);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['tag' => ['required', 'string', 'max:40']]);
        $tag = Tag::where('name', Tag::canonicalName($data['tag']))->firstOrFail();
        $request->user()->followedTags()->detach($tag->id);

        return back();
    }
}
