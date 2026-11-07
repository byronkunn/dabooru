<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\KnowledgeRevision;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\WikiPage;
use App\Support\KnowledgeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Support\StaffAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class KnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', Rule::in(Tag::CATEGORIES)],
            'needs_wiki' => ['nullable', 'boolean'],
        ]);
        $tags = $this->tags($request)
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$term.'%')
                ->orWhereIn('id', DB::table('tag_aliases')->where('name', 'like', '%'.$term.'%')->select('tag_id'))))
            ->when($data['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($data['needs_wiki'] ?? false, fn ($q) => $q->where(fn ($q) => $q->whereNull('wiki')->orWhere('wiki', '')))
            ->withCount(['posts' => fn ($q) => $q->visibleTo($request->user())->visibleRating($this->explicit($request))])
            ->orderBy('name')->paginate(40)->withQueryString();

        return Inertia::render('Knowledge/Index', [
            'tags' => $tags, 'categories' => Tag::CATEGORIES, 'filters' => $data,
            'articles' => WikiPage::when(! $this->explicit($request), fn ($q) => $q->where('rating', '!=', 'explicit'))
                ->orderBy('title')->paginate(20, ['*'], 'articles_page')->withQueryString(),
            'canContribute' => $request->user() && KnowledgeService::canContribute($request->user()),
        ]);
    }

    public function autocomplete(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:40']]);

        return response()->json(['tags' => $this->tags($request)
            ->where(fn ($q) => $q->where('name', 'like', '%'.($data['q'] ?? '').'%')
                ->orWhere('name', Tag::canonicalName($data['q'] ?? ''))
                ->orWhereIn('id', DB::table('tag_aliases')->where('name', 'like', '%'.($data['q'] ?? '').'%')->select('tag_id')))
            ->withCount(['posts' => fn ($q) => $q->visibleTo($request->user())->visibleRating($this->explicit($request))])
            ->orderByDesc('posts_count')->limit(8)->get()
            ->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name, 'category' => $tag->category,
                'definition' => Str::limit(strip_tags($tag->wiki ?? ''), 100),
                'count' => $tag->posts_count, 'url' => route('knowledge.show', ['type' => 'tag', 'id' => $tag->id], false)])]);
    }

    public function show(Request $request, string $type, int $id)
    {
        $target = KnowledgeService::target($type, $id);
        abort_if($type === 'bulk', 404);
        if ($target instanceof Tag && $target->merged_into_id) {
            $canonical = Tag::where('name', Tag::canonicalName($target->name))->firstOrFail();

            return redirect()->route('knowledge.show', ['type' => 'tag', 'id' => $canonical->id]);
        }
        $this->visible($request, $target);
        $state = KnowledgeService::snapshot($target);
        $works = $target instanceof Tag
            ? $target->posts()->visibleTo($request->user())->visibleRating($this->explicit($request))
                ->with(['user', 'media.media', 'chapter.comic'])->latest()->paginate(24)->withQueryString()
            : null;
        if ($works) {
            $works->setCollection(collect(PostResource::collection($works->getCollection())->resolve()));
        }
        $revisions = KnowledgeRevision::where('target_id', $id)
            ->whereIn('target_type', $type === 'tag' ? ['tag', 'bulk'] : [$type])
            ->where(fn ($q) => $q->whereIn('status', ['accepted', 'reverted'])
                ->when($request->user(), fn ($q) => $q->orWhere('user_id', $request->user()->id))
                ->when($this->canCurate($request), fn ($q) => $q->orWhere('status', 'pending')))
            ->with(['user', 'reviewer'])->latest()->paginate(20, ['*'], 'history_page')->withQueryString();
        $revisions->setCollection($revisions->getCollection()->filter(fn ($revision) => $this->revisionVisible($request, $revision))
            ->map(fn ($revision) => $this->revisionData($revision)));

        return Inertia::render('Knowledge/Show', [
            'type' => $type, 'targetId' => $id, 'state' => $state,
            'version' => KnowledgeService::version($target), 'locked' => (bool) $target->locked,
            'html' => $this->markdown($state['wiki'] ?? $state['body'] ?? ''),
            'categories' => Tag::CATEGORIES, 'works' => $works,
            'relatedTags' => $target instanceof Tag
                ? $this->tags($request)->whereIn('id', array_merge($state['related'], $state['implies']))->get(['id', 'name', 'category'])
                : [],
            'member' => $target instanceof Tag && $target->artist_status === 'member' && $target->member_id
                ? User::find($target->member_id)?->only(['username', 'name']) : null,
            'history' => $revisions,
            'discussions' => DB::table('knowledge_discussions')->leftJoin('users', 'users.id', '=', 'knowledge_discussions.user_id')
                ->where('target_type', $type)->where('target_id', $id)->where('hidden', false)
                ->select('knowledge_discussions.id', 'body', 'knowledge_discussions.created_at', 'username')
                ->orderByDesc('knowledge_discussions.id')->paginate(20, ['*'], 'discussion_page')->withQueryString(),
            'watching' => $request->user() && DB::table('knowledge_watches')->where('user_id', $request->user()->id)
                ->where('target_type', $type)->where('target_id', $id)->exists(),
            'canContribute' => $request->user() && KnowledgeService::canContribute($request->user()),
            'trusted' => $request->user() && KnowledgeService::trusted($request->user()),
            'initialTab' => in_array($request->query('tab'), ['wiki', 'artwork', 'discussion', 'history', 'edit'], true)
                ? $request->query('tab') : 'wiki',
        ]);
    }

    public function create(Request $request)
    {
        $this->contributor($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['tag', 'wiki'])],
            'name' => ['required', 'string', 'max:160'],
        ]);
        if ($data['type'] === 'tag') {
            $names = Tag::parse($data['name']);
            if (count($names) !== 1) {
                throw ValidationException::withMessages(['name' => 'Enter one tag name.']);
            }
            $name = Tag::canonicalName($names[0]);
            $target = Tag::firstOrCreate(['name' => $name]);
        } else {
            $target = WikiPage::firstOrCreate(['title' => Str::squish($data['name'])]);
        }

        return redirect()->route('knowledge.show', ['type' => $data['type'], 'id' => $target->id])
            ->with('success', 'Draft page ready. Submit its definition for review.');
    }

    public function propose(Request $request, string $type, int $id)
    {
        $this->contributor($request);
        abort_unless(in_array($type, ['tag', 'wiki', 'post'], true), 404);
        $target = KnowledgeService::target($type, $id);
        $this->visible($request, $target);
        $common = $request->validate([
            'summary' => ['required', 'string', 'min:3', 'max:500'],
            'base_version' => ['required', 'integer', 'min:0'],
        ]);
        $after = KnowledgeService::snapshot($target);
        if ($type === 'post') {
            $data = $request->validate(['tags' => ['present', 'array', 'max:50'], 'tags.*' => ['string', 'max:40', 'distinct']]);
            $after = ['tags' => Tag::expandedNames($this->names($data['tags']))];
        } elseif ($type === 'wiki') {
            $after = $request->validate([
                'title' => ['required', 'string', 'max:160', Rule::unique('wiki_pages')->ignore($id)],
                'body' => ['required', 'string', 'max:20000'],
                'rating' => ['required', Rule::in(User::RATINGS)],
            ]);
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:40'],
                'category' => ['required', Rule::in(Tag::CATEGORIES)],
                'wiki' => ['nullable', 'string', 'max:20000'],
                'wiki_rating' => ['required', Rule::in(User::RATINGS)],
                'source_url' => ['nullable', 'url:http,https', 'max:500'],
                'aliases' => ['present', 'array', 'max:10'],
                'aliases.*' => ['string', 'max:40', 'distinct'],
                'related' => ['present', 'array', 'max:20'],
                'related.*' => ['integer', 'distinct', 'exists:tags,id'],
                'implies' => ['present', 'array', 'max:10'],
                'implies.*' => ['integer', 'distinct', 'exists:tags,id'],
                'merged_into_id' => ['nullable', 'integer', 'exists:tags,id'],
            ]);
            $data['name'] = $this->names([$data['name']])[0];
            $data['aliases'] = $this->names($data['aliases']);
            if (! $request->user()->isAdmin() && $target->category === 'artist'
                && $target->artist_status !== 'unverified' && $data['category'] !== 'artist') {
                throw ValidationException::withMessages(['category' => 'Only moderators can reclassify confirmed artist pages.']);
            }
            $after = array_replace($after, $data);
            if ($request->user()->isAdmin()) {
                $verification = $request->validate([
                    'artist_status' => ['required', Rule::in(['unverified', 'external', 'member'])],
                    'member_id' => ['nullable', 'integer', 'exists:users,id'],
                ]);
                if ($verification['artist_status'] === 'member') {
                    abort_unless($verification['member_id'] && User::verified()->whereKey($verification['member_id'])->exists(), 422, 'Choose a verified member.');
                } else {
                    $verification['member_id'] = null;
                }
                $after = array_replace($after, $verification);
            }
            if ($after['category'] !== 'artist') {
                $after['artist_status'] = 'unverified';
                $after['member_id'] = null;
            }
        }
        $revision = KnowledgeService::propose($request->user(), $type, $id, $after, $common['base_version'], $common['summary']);

        return back()->with('success', $revision->status === 'accepted' ? 'Edit published.' : 'Edit submitted for moderator review.');
    }

    public function bulk(Request $request, int $id)
    {
        $this->contributor($request);
        $data = $request->validate([
            'replacement_id' => ['required', 'integer', 'exists:tags,id'],
            'post_ids' => ['required', 'array', 'min:1', 'max:100'],
            'post_ids.*' => ['integer', 'distinct', 'exists:posts,id'],
            'summary' => ['required', 'string', 'min:3', 'max:500'],
            'base_version' => ['required', 'integer', 'min:0'],
        ]);
        $tag = Tag::findOrFail($id);
        $destination = Tag::findOrFail($data['replacement_id']);
        $this->visible($request, $tag);
        $this->visible($request, $destination);
        abort_if($destination->id === $tag->id || $destination->merged_into_id, 422, 'Choose another canonical tag.');
        DB::transaction(function () use ($request, $id, $data, $tag, $destination) {
            $tag = Tag::lockForUpdate()->findOrFail($id);
            $posts = Post::visibleTo($request->user())->visibleRating($this->explicit($request))
                ->whereIn('id', $data['post_ids'])->whereHas('tags', fn ($q) => $q->where('tags.id', $id))->lockForUpdate()->get();
            abort_unless($posts->count() === count($data['post_ids']), 422, 'Choose visible works that contain this tag.');
            $after = ['posts' => $posts->mapWithKeys(fn ($post) => [$post->id => [
                'tags' => Tag::expandedNames(array_map(fn ($name) => $name === $tag->name ? $destination->name : $name, $post->tags()->pluck('name')->all())),
            ]])->all()];
            KnowledgeService::propose($request->user(), 'bulk', $id, $after, $data['base_version'], $data['summary']);
        });

        return back()->with('success', 'Bulk proposal recorded. Review its affected-work diff before approval.');
    }

    public function revision(Request $request, KnowledgeRevision $revision)
    {
        $this->canReadRevision($request, $revision);

        return Inertia::render('Knowledge/Revision', ['revision' => $this->revisionData($revision->load(['user', 'reviewer']))]);
    }

    public function changes(Request $request)
    {
        $query = KnowledgeRevision::whereIn('status', ['accepted', 'reverted'])->with(['user', 'reviewer'])->latest();
        $revisions = $query->paginate(30)->withQueryString();
        $revisions->setCollection($revisions->getCollection()->filter(fn ($r) => $this->revisionVisible($request, $r))->map(fn ($r) => $this->revisionData($r)));

        return Inertia::render('Knowledge/Changes', ['revisions' => $revisions, 'moderation' => false, 'reports' => []]);
    }

    public function moderate(Request $request)
    {
        $revisions = KnowledgeRevision::where('status', 'pending')->with(['user', 'reviewer'])->oldest()->paginate(30)->withQueryString();
        $revisions->setCollection($revisions->getCollection()->filter(fn ($r) => $this->revisionVisible($request, $r))->map(fn ($r) => $this->revisionData($r)));

        $reports = DB::table('knowledge_reports')->where('resolved', false)->orderBy('id')->paginate(20, ['*'], 'reports_page')->withQueryString();
        $reports->getCollection()->transform(function ($report) {
            $report->target_url = null;
            if ($report->target_type === 'revision') {
                $report->target_url = route('knowledge.revision', $report->target_id, false);
            } elseif ($report->target_type === 'discussion') {
                $discussion = DB::table('knowledge_discussions')->where('id', $report->target_id)->first();
                if ($discussion) {
                    $report->target_url = route('knowledge.show', ['type' => $discussion->target_type, 'id' => $discussion->target_id, 'tab' => 'discussion'], false);
                }
            } else {
                $report->target_url = route('knowledge.show', ['type' => $report->target_type, 'id' => $report->target_id], false);
            }

            return $report;
        });

        return Inertia::render('Knowledge/Changes', ['revisions' => $revisions, 'moderation' => true, 'reports' => $reports]);
    }

    public function review(Request $request, KnowledgeRevision $revision)
    {
        $this->canReadRevision($request, $revision);
        $data = $request->validate([
            'action' => ['required', Rule::in(['accept', 'reject', 'revert'])],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        if ($data['action'] === 'accept') {
            KnowledgeService::apply($revision, $request->user());
        } elseif ($data['action'] === 'revert') {
            KnowledgeService::revert($revision, $request->user(), $data['reason']);
        } else {
            DB::transaction(function () use ($request, $revision, $data) {
                $revision = KnowledgeRevision::lockForUpdate()->findOrFail($revision->id);
                abort_unless($revision->status === 'pending', 409, 'This revision has already been reviewed.');
                $revision->update(['status' => 'rejected', 'reviewer_id' => $request->user()->id, 'review_reason' => $data['reason']]);
                KnowledgeService::notify($revision, 'rejected');
            });
        }
        if ($data['action'] === 'accept') {
            $revision->update(['review_reason' => $data['reason']]);
        }
        ModerationAction::record($request->user(), 'knowledge_'.$data['action'], 'revision', $revision->id, null, $data['reason']);

        return back()->with('success', 'Revision reviewed.');
    }

    public function watch(Request $request, string $type, int $id)
    {
        $target = KnowledgeService::target($type, $id);
        abort_unless(in_array($type, ['tag', 'wiki', 'post'], true), 404);
        $this->visible($request, $target);
        $data = $request->validate(['watching' => ['required', 'boolean']]);
        $keys = ['user_id' => $request->user()->id, 'target_type' => $type, 'target_id' => $id];
        if ($data['watching']) {
            DB::table('knowledge_watches')->insertOrIgnore($keys);
        } else {
            DB::table('knowledge_watches')->where($keys)->delete();
        }

        return back()->with('success', $data['watching'] ? 'Watching community changes.' : 'Stopped watching.');
    }

    public function discuss(Request $request, string $type, int $id)
    {
        $this->contributor($request);
        abort_unless(in_array($type, ['tag', 'wiki', 'post'], true), 404);
        $target = KnowledgeService::target($type, $id);
        $this->visible($request, $target);
        KnowledgeService::checkEditable($target, $request->user());
        $data = $request->validate(['body' => ['required', 'string', 'min:3', 'max:3000']]);
        DB::table('knowledge_discussions')->insert($data + [
            'target_type' => $type, 'target_id' => $id, 'user_id' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Discussion added.');
    }

    public function report(Request $request)
    {
        $this->contributor($request);
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['tag', 'wiki', 'post', 'revision', 'discussion'])],
            'target_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        if ($data['target_type'] === 'revision') {
            $this->canReadRevision($request, KnowledgeRevision::findOrFail($data['target_id']));
        } elseif ($data['target_type'] === 'discussion') {
            $discussion = DB::table('knowledge_discussions')->where('id', $data['target_id'])->first();
            abort_unless($discussion && ! $discussion->hidden, 404);
            $this->visible($request, KnowledgeService::target($discussion->target_type, $discussion->target_id));
        } else {
            $this->visible($request, KnowledgeService::target($data['target_type'], $data['target_id']));
        }
        DB::table('knowledge_reports')->insert($data + ['user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Report sent to moderators.');
    }

    public function resolveReport(Request $request, int $id)
    {
        $data = $request->validate(['hide_discussion' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $id, $data) {
            $report = DB::table('knowledge_reports')->where('id', $id)->lockForUpdate()->first();
            abort_unless($report, 404);
            abort_if($report->resolved, 409, 'This report is already closed.');
            if ($data['hide_discussion'] && $report->target_type === 'discussion') {
                DB::table('knowledge_discussions')->where('id', $report->target_id)->update(['hidden' => true]);
            }
            DB::table('knowledge_reports')->where('id', $id)->update(['resolved' => true, 'updated_at' => now()]);
            ModerationAction::record($request->user(), 'knowledge_resolve_report', $report->target_type, $report->target_id, null, null, ['knowledge_report_id' => $id]);
        });

        return back()->with('success', 'Report resolved.');
    }

    public function lock(Request $request, string $type, int $id)
    {
        abort_unless(in_array($type, ['tag', 'wiki'], true), 404);
        $data = $request->validate(['locked' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $type, $id, $data) {
            $target = KnowledgeService::target($type, $id, true);
            $target->locked = $data['locked'];
            $target->version++;
            $target->save();
            ModerationAction::record($request->user(), $data['locked'] ? 'knowledge_lock' : 'knowledge_unlock', $type, $id, null);
        });

        return back()->with('success', $data['locked'] ? 'Page locked.' : 'Page unlocked.');
    }

    public function restrict(Request $request, User $user)
    {
        $data = $request->validate(['restricted' => ['required', 'boolean']]);
        abort_if($user->isAdmin(), 422, 'Administrator permissions must be managed separately.');
        abort_if(! $request->user()->isAdmin() && ($user->staff_roles ?? []) !== [], 403, 'Only administrators can restrict staff.');
        $user->forceFill(['contributions_restricted' => $data['restricted']])->save();
        ModerationAction::record($request->user(), 'knowledge_restrict', 'user', $user->id, null, null, $data);

        return back()->with('success', 'Contributor permissions updated.');
    }

    private function contributor(Request $request): void
    {
        abort_unless(KnowledgeService::canContribute($request->user()), 403, 'Verify your email before contributing. Restricted accounts cannot contribute.');
    }

    private function names(array $input): array
    {
        $names = [];
        foreach ($input as $name) {
            $name = Str::lower(Str::squish($name));
            if ($name === '' || str_contains($name, ',')) {
                throw ValidationException::withMessages(['tags' => 'Each tag must be a nonempty name without commas.']);
            }
            $names[] = $name;
        }
        sort($names);

        return array_values(array_unique($names));
    }

    private function explicit(Request $request): bool
    {
        return (bool) $request->user()?->show_explicit;
    }

    private function tags(Request $request)
    {
        return Tag::whereNull('merged_into_id')->when(! $this->explicit($request), fn ($q) => $q->where('wiki_rating', '!=', 'explicit'));
    }

    private function visible(Request $request, Model $target): void
    {
        $rating = $target instanceof Tag ? $target->wiki_rating : $target->rating;
        abort_if($rating === 'explicit' && ! $this->explicit($request), 403, 'Enable explicit content in settings to view this content.');
        if ($target instanceof Post) {
            abort_unless(Post::visibleTo($request->user())->whereKey($target->id)->exists(), 404);
        }
    }

    private function revisionVisible(Request $request, KnowledgeRevision $revision): bool
    {
        if (! $this->explicit($request)) {
            foreach ([$revision->before, $revision->after] as $snapshot) {
                if (($snapshot['wiki_rating'] ?? $snapshot['rating'] ?? null) === 'explicit') {
                    return false;
                }
            }
        }
        $target = match ($revision->target_type) {
            'tag', 'bulk' => Tag::find($revision->target_id),
            'wiki' => WikiPage::find($revision->target_id),
            'post' => Post::visibleTo($request->user())->visibleRating($this->explicit($request))->find($revision->target_id),
        };
        if (! $target || (! $this->explicit($request) && ($target->wiki_rating ?? $target->rating) === 'explicit')) {
            return false;
        }
        if ($revision->target_type === 'bulk') {
            $ids = array_keys($revision->after['posts']);

            return Post::visibleTo($request->user())->visibleRating($this->explicit($request))->whereIn('id', $ids)->count() === count($ids);
        }

        return true;
    }

    private function canReadRevision(Request $request, KnowledgeRevision $revision): void
    {
        abort_unless(in_array($revision->status, ['accepted', 'reverted'], true)
            || $this->canCurate($request) || $request->user()?->id === $revision->user_id, 403);
        abort_unless($this->revisionVisible($request, $revision), 403, 'This revision is hidden by your content or blocking preferences.');
    }

    private function canCurate(Request $request): bool
    {
        return $request->user() && in_array('curator', StaffAccess::permissions($request->user()), true);
    }

    private function revisionData(KnowledgeRevision $revision): array
    {
        return $revision->only(['id', 'target_type', 'target_id', 'before', 'after', 'summary', 'status', 'review_reason', 'created_at']) + [
            'editor' => $revision->user?->username, 'reviewer' => $revision->reviewer?->username,
            'url' => route('knowledge.revision', $revision->id, false),
            'target_url' => route('knowledge.show', ['type' => $revision->target_type === 'bulk' ? 'tag' : $revision->target_type, 'id' => $revision->target_id], false),
        ];
    }

    private function markdown(string $body): string
    {
        $html = Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 20]);

        return preg_replace('/<img\b[^>]*>/i', '', $html);
    }
}
