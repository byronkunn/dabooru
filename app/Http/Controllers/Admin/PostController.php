<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Support\Moderation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    public function restore(Request $request, int $post): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $post, $data) {
            $work = Post::onlyTrashed()->lockForUpdate()->findOrFail($post);
            Moderation::restore($work);
            ModerationAction::record($request->user(), 'restore_post', Post::class, $work->id, null, $data['reason']);
        });

        return back()->with('success', 'Work restored.');
    }
}
