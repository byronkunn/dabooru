<?php

namespace App\Http\Controllers;

use App\Models\ArtistCreditClaim;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ArtistCreditController extends Controller
{
    public function store(Request $request, Post $post)
    {
        $artist = $request->user();
        abort_unless($artist->email_verified_at && ! $artist->isBanned(), 403);
        abort_unless(Post::visibleTo($artist)->visibleRating((bool) $artist->show_explicit)->whereKey($post->id)->exists(), 404);
        $data = $request->validate(['evidence' => ['required', 'string', 'min:10', 'max:2000']]);
        DB::transaction(function () use ($artist, $post, $data) {
            $post = Post::lockForUpdate()->findOrFail($post->id);
            abort_unless($post->isExternalArtist() && ! $post->credited_artist_id, 409, 'This work is not accepting credit claims.');
            $claim = ArtistCreditClaim::firstOrCreate(
                ['user_id' => $artist->id, 'post_id' => $post->id],
                ['evidence' => $data['evidence']],
            );
            if (! $claim->wasRecentlyCreated && $claim->status === 'rejected') {
                $claim->update(['status' => 'pending', 'reason' => null, 'evidence' => $data['evidence']]);
            }
        });

        return back()->with('success', 'Credit claim submitted for review.');
    }

    public function index(Request $request)
    {
        $status = $request->query('status') === 'reviewed' ? 'reviewed' : 'pending';
        $claims = ArtistCreditClaim::query()
            ->when($status === 'pending', fn ($query) => $query->where('status', 'pending'), fn ($query) => $query->where('status', '!=', 'pending'))
            ->oldest()->orderBy('id')->simplePaginate(30)->withQueryString();
        $posts = Post::whereIn('id', $claims->getCollection()->pluck('post_id'))->get(['id', 'title', 'artist_name', 'source_url', 'credited_artist_id'])->keyBy('id');
        $users = User::whereIn('id', $claims->getCollection()->pluck('user_id'))->get(['id', 'username', 'email_verified_at', 'banned_at'])->keyBy('id');

        return Inertia::render('Admin/CreditClaims', [
            'status' => $status,
            'claims' => $claims->getCollection()->map(fn ($claim) => $claim->only(['id', 'evidence', 'reason', 'status', 'post_id']) + [
                'post' => $posts->get($claim->post_id),
                'artist' => $users->get($claim->user_id)?->username,
                'approvable' => $posts->has($claim->post_id) && ! $posts->get($claim->post_id)->credited_artist_id
                    && $users->get($claim->user_id)?->email_verified_at && ! $users->get($claim->user_id)?->banned_at,
            ]),
            'nextPage' => $claims->nextPageUrl(), 'previousPage' => $claims->previousPageUrl(),
        ]);
    }

    public function review(Request $request, ArtistCreditClaim $claim)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected', 'revoked'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($request, $claim, $data) {
            $post = Post::withTrashed()->lockForUpdate()->find($claim->post_id);
            $claim = ArtistCreditClaim::lockForUpdate()->findOrFail($claim->id);
            abort_if($claim->user_id === $request->user()->id, 403, 'Another curator must review your claim.');
            if ($data['decision'] === 'revoked') {
                abort_unless($claim->status === 'approved' && $post?->credited_artist_id === $claim->user_id, 409);
                $post->forceFill(['credited_artist_id' => null])->save();
            } else {
                abort_unless($claim->status === 'pending', 409, 'This claim has already been reviewed.');
                if ($data['decision'] === 'approved') {
                    $artist = User::findOrFail($claim->user_id);
                    abort_unless($post && ! $post->trashed() && $post->isExternalArtist() && ! $post->credited_artist_id
                        && $artist->email_verified_at && ! $artist->isBanned(), 409);
                    $post->forceFill(['credited_artist_id' => $artist->id])->save();
                }
            }

            $claim->update(['status' => $data['decision'], 'reason' => $data['reason']]);
            ModerationAction::record($request->user(), 'artist_credit_'.$data['decision'], Post::class, $claim->post_id, null, $data['reason'],
                ['claim_id' => $claim->id, 'artist_id' => $claim->user_id]);
        });

        return back()->with('success', 'Credit claim updated.');
    }
}
