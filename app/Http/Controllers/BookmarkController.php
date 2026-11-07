<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        $post->bookmarkedBy()->syncWithoutDetaching([$request->user()->id]);

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $post->bookmarkedBy()->detach($request->user()->id);

        return back();
    }
}
