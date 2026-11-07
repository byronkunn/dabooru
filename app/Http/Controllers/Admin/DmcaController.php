<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DmcaNotice;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Support\Moderation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DmcaController extends Controller
{
    private const STATUSES = ['open', 'actioned', 'resolved', 'rejected'];

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : 'open';
        $notices = DmcaNotice::where('status', $status)->with('handler:id,username')
            ->oldest()->orderBy('id')->simplePaginate(30)->withQueryString();
        $postIds = $notices->getCollection()->map(fn (DmcaNotice $notice) => $notice->post_id ?? $this->postId($notice))->filter();
        $posts = Post::withTrashed()->whereIn('id', $postIds)->get(['id', 'title', 'deleted_at', 'moderation_hidden_at'])->keyBy('id');

        return Inertia::render('Admin/Dmca', [
            'status' => $status,
            'counts' => DmcaNotice::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'notices' => $notices->getCollection()->map(function (DmcaNotice $notice) use ($posts) {
                $post = $posts->get($notice->post_id ?? $this->postId($notice));

                return $notice->only(['id', 'reporter_name', 'reporter_email', 'rights_holder', 'url', 'description', 'status', 'resolution']) + [
                    'received' => $notice->created_at->toIso8601String(),
                    'handler' => $notice->handler?->username,
                    'claimed_by' => $notice->handled_by,
                    'post' => $post ? [
                        'id' => $post->id,
                        'title' => $post->title,
                        'visible' => ! $post->trashed(),
                        'restorable' => $post->trashed() && $post->moderation_hidden_at !== null,
                    ] : null,
                ];
            }),
            'previousPage' => $notices->previousPageUrl(),
            'nextPage' => $notices->nextPageUrl(),
        ]);
    }

    public function update(Request $request, DmcaNotice $notice): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['claim', 'release', 'takedown', 'resolve', 'reject'])],
            'reason' => ['required_unless:action,claim,release', 'nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $notice, $data) {
            $notice = DmcaNotice::lockForUpdate()->findOrFail($notice->id);
            $action = $data['action'];
            $actor = $request->user();
            $reason = $data['reason'] ?? null;
            abort_unless($notice->status === 'open' || ($notice->status === 'actioned' && $action === 'resolve'), 409, 'This notice has already been reviewed.');

            if (in_array($action, ['claim', 'release'], true)) {
                $notice->update(['handled_by' => $action === 'claim' ? $actor->id : null]);
            } else {
                $status = match ($action) {
                    'takedown' => 'actioned',
                    'reject' => 'rejected',
                    'resolve' => 'resolved',
                };
                if ($action === 'takedown') {
                    $post = Post::withTrashed()->lockForUpdate()->find($this->postId($notice));
                    abort_unless($post, 422, 'The notice does not match a work on this site.');
                    Moderation::hide($post);
                    $notice->post_id = $post->id;
                }

                $notice->update([
                    'status' => $status, 'handled_by' => $actor->id,
                    'resolved_at' => now(), 'resolution' => $reason,
                ]);
            }

            ModerationAction::record($actor, 'dmca_'.$action, DmcaNotice::class, $notice->id, null, $reason,
                ['post_id' => $notice->post_id]);
        });

        return back()->with('success', 'Notice updated.');
    }

    private function postId(DmcaNotice $notice): ?int
    {
        $url = parse_url($notice->url);
        $site = parse_url(config('app.url'));
        if (! isset($url['host'], $site['host']) || strcasecmp($url['host'], $site['host']) !== 0
            || ! in_array($url['scheme'] ?? null, ['http', 'https'], true)
            || isset($url['user']) || isset($url['pass'])) {
            return null;
        }
        $port = static fn (array $parts) => $parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80);
        if ($port($url) !== $port($site)) {
            return null;
        }

        $prefix = rtrim($site['path'] ?? '', '/');
        return preg_match('~^'.preg_quote($prefix, '~').'/p/([1-9][0-9]*)/?$~D', $url['path'] ?? '', $matches)
            ? filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null
            : null;
    }
}
