<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\KnowledgeRevision;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\WikiPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KnowledgeService
{
    public static function canContribute(User $user): bool
    {
        return ! $user->isBanned() && ! $user->isUnsorted()
            && ! $user->contributions_restricted && $user->email_verified_at !== null;
    }

    public static function trusted(User $user): bool
    {
        return $user->isAdmin() || KnowledgeRevision::where('user_id', $user->id)
            ->where('status', 'accepted')->whereNotNull('reviewer_id')
            ->whereColumn('reviewer_id', '!=', 'user_id')->count() >= 5;
    }

    public static function target(string $type, int $id, bool $lock = false): Model
    {
        $class = match ($type) {
            'tag', 'bulk' => Tag::class,
            'wiki' => WikiPage::class,
            'post' => Post::class,
            default => abort(404),
        };

        return $class::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
    }

    public static function version(Model $target): int
    {
        return (int) ($target instanceof Post ? $target->tag_version : $target->version);
    }

    public static function snapshot(Model $target): array
    {
        if ($target instanceof Post) {
            return ['tags' => $target->tags()->pluck('name')->all()];
        }
        if ($target instanceof WikiPage) {
            return $target->only(['title', 'body', 'rating']);
        }

        return $target->only(['name', 'category', 'wiki', 'wiki_rating', 'artist_status', 'member_id', 'source_url', 'merged_into_id']) + [
            'aliases' => DB::table('tag_aliases')->where('tag_id', $target->id)->orderBy('name')->pluck('name')->all(),
            'related' => DB::table('tag_relations')->where('tag_id', $target->id)->where('kind', 'related')->orderBy('related_tag_id')->pluck('related_tag_id')->all(),
            'implies' => DB::table('tag_relations')->where('tag_id', $target->id)->where('kind', 'implies')->orderBy('related_tag_id')->pluck('related_tag_id')->all(),
        ];
    }

    public static function propose(User $user, string $type, int $id, array $after, int $base, string $summary): KnowledgeRevision
    {
        abort_unless(self::canContribute($user), 403, 'Verify your email before contributing. Restricted accounts cannot contribute.');

        return DB::transaction(function () use ($user, $type, $id, $after, $base, $summary) {
            $target = self::target($type, $id, true);
            self::checkEditable($target, $user);
            self::checkVersion($target, $base);
            $before = self::snapshot($target);
            if ($type === 'bulk') {
                $before = ['posts' => []];
                foreach (array_keys($after['posts']) as $postId) {
                    $post = Post::lockForUpdate()->findOrFail($postId);
                    $before['posts'][$postId] = self::snapshot($post);
                }
            }
            if ($before == $after) {
                throw ValidationException::withMessages(['summary' => 'Make a change before submitting.']);
            }
            $revision = KnowledgeRevision::create([
                'target_type' => $type, 'target_id' => $id, 'user_id' => $user->id,
                'before' => $before, 'after' => $after, 'base_version' => $base,
                'summary' => $summary, 'status' => 'pending',
            ]);
            $structural = $type === 'bulk' || ($type === 'tag' && collect(['name', 'category', 'aliases', 'implies', 'related', 'merged_into_id'])
                ->contains(fn ($key) => $before[$key] != $after[$key]));
            if ($type !== 'bulk' && ($user->isAdmin() || (self::trusted($user) && ! $structural))) {
                self::apply($revision, $user);
            }

            return $revision->fresh();
        });
    }

    public static function checkEditable(Model $target, User $user): void
    {
        abort_if(! ($target instanceof Post) && $target->locked && ! $user->isAdmin(), 423, 'This page is locked by a moderator.');
        abort_if($target instanceof Tag && $target->merged_into_id, 422, 'Edit the canonical tag instead.');
    }

    private static function checkVersion(Model $target, int $base): void
    {
        if (self::version($target) !== $base) {
            throw ValidationException::withMessages(['base_version' => 'This content changed. Reload and compare the latest revision before resubmitting.']);
        }
    }

    public static function apply(KnowledgeRevision $revision, User $reviewer, bool $restore = false): void
    {
        DB::transaction(function () use ($revision, $reviewer, $restore) {
            $revision = KnowledgeRevision::lockForUpdate()->findOrFail($revision->id);
            abort_unless($revision->status === 'pending', 409, 'This revision has already been reviewed.');
            $target = self::target($revision->target_type, $revision->target_id, true);
            self::checkEditable($target, $reviewer);
            self::checkVersion($target, $revision->base_version);
            $after = $revision->after;
            if ($revision->target_type === 'bulk') {
                foreach ($revision->before['posts'] as $id => $snapshot) {
                    $post = Post::lockForUpdate()->findOrFail($id);
                    if (self::snapshot($post) != $snapshot) {
                        throw ValidationException::withMessages(['revision' => "Work {$id} changed. Submit a fresh bulk proposal."]);
                    }
                    if (! $restore && Tag::expandedNames($after['posts'][$id]['tags']) !== $after['posts'][$id]['tags']) {
                        throw ValidationException::withMessages(['revision' => 'Tag relationships changed since this preview. Submit a fresh proposal.']);
                    }
                    Tag::syncFor($post, $after['posts'][$id]['tags'], false, ! $restore);
                }
                $target->increment('version');
            } elseif ($target instanceof Post) {
                if (! $restore && Tag::expandedNames($after['tags']) !== $after['tags']) {
                    throw ValidationException::withMessages(['revision' => 'Tag relationships changed since this preview. Submit a fresh proposal.']);
                }
                Tag::syncFor($target, $after['tags'], false, ! $restore);
            } elseif ($target instanceof WikiPage) {
                $target->fill($after);
                $target->version++;
                $target->save();
            } else {
                self::applyTag($target, $after);
            }
            $revision->update(['status' => 'accepted', 'reviewer_id' => $reviewer->id]);
            // Store the applied state, including automatically implied tags, for accurate rollback.
            $revision->update(['after' => $revision->target_type === 'bulk'
                ? ['posts' => collect(array_keys($after['posts']))->mapWithKeys(fn ($id) => [$id => self::snapshot(Post::findOrFail($id))])->all()]
                : self::snapshot($target->fresh())]);
            self::notify($revision, 'accepted');
        });
    }

    private static function applyTag(Tag $tag, array $after): void
    {
        if ($tag->name !== $after['name'] && ! in_array($tag->name, $after['aliases'], true)) {
            $after['aliases'][] = $tag->name;
            sort($after['aliases']);
        }
        $names = array_merge([$after['name']], $after['aliases']);
        if (count($names) !== count(array_unique($names))) {
            throw ValidationException::withMessages(['aliases' => 'Aliases must differ from the canonical name.']);
        }
        foreach ($names as $name) {
            if (Tag::where('name', $name)->whereKeyNot($tag->id)->exists()
                || DB::table('tag_aliases')->where('name', $name)->where('tag_id', '!=', $tag->id)->exists()) {
                throw ValidationException::withMessages(['aliases' => "The name {$name} belongs to another tag. Propose a merge instead."]);
            }
        }

        foreach (array_merge($after['related'], $after['implies']) as $id) {
            $other = Tag::findOrFail($id);
            if ($other->id === $tag->id || $other->merged_into_id) {
                throw ValidationException::withMessages(['implies' => 'Use distinct, canonical related tags.']);
            }
        }
        foreach ($after['implies'] as $id) {
            $frontier = [$id];
            $seen = [];
            while ($frontier !== []) {
                if (in_array($tag->id, $frontier)) {
                    throw ValidationException::withMessages(['implies' => 'Tag implications cannot form a cycle.']);
                }
                $seen = array_unique(array_merge($seen, $frontier));
                $frontier = array_values(array_diff(DB::table('tag_relations')
                    ->where('kind', 'implies')->whereIn('tag_id', $frontier)->pluck('related_tag_id')->all(), $seen));
            }
        }
        $merged = $after['merged_into_id'];
        if ($merged) {
            $destination = Tag::findOrFail($merged);
            if ($destination->id === $tag->id || $destination->merged_into_id
                || DB::table('post_tag')->where('tag_id', $tag->id)->exists()
                || DB::table('tag_relations')->where('related_tag_id', $tag->id)->orWhere('tag_id', $tag->id)->exists()) {
                throw ValidationException::withMessages(['merged_into_id' => 'Bulk retag affected works and remove relationships before merging into a distinct canonical tag.']);
            }
        }
        $fields = collect($after)->except(['aliases', 'related', 'implies'])->all();
        $tag->fill($fields);
        $tag->version++;
        $tag->save();
        if ($merged) {
            DB::table('tag_user')->insertOrIgnoreUsing(
                ['tag_id', 'user_id'],
                DB::table('tag_user')->where('tag_id', $tag->id)->selectRaw('? as tag_id, user_id', [$merged])
            );
            DB::table('tag_user')->where('tag_id', $tag->id)->delete();
        }
        DB::table('tag_aliases')->where('tag_id', $tag->id)->delete();
        foreach ($after['aliases'] as $name) {
            DB::table('tag_aliases')->insert(['name' => $name, 'tag_id' => $tag->id]);
        }
        DB::table('tag_relations')->where('tag_id', $tag->id)->delete();
        foreach (['related', 'implies'] as $kind) {
            foreach (array_unique($after[$kind]) as $id) {
                DB::table('tag_relations')->insert(['tag_id' => $tag->id, 'related_tag_id' => $id, 'kind' => $kind]);
            }
        }
    }

    public static function mergeTags(User $admin, int $sourceId, int $destinationId, int $baseVersion, int $destinationVersion, string $reason): void
    {
        abort_unless($admin->isAdmin() && self::canContribute($admin), 403);
        DB::transaction(function () use ($admin, $sourceId, $destinationId, $baseVersion, $destinationVersion, $reason) {
            $tags = Tag::whereIn('id', [$sourceId, $destinationId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $tags->get($sourceId);
            $destination = $tags->get($destinationId);
            abort_unless($source && $destination, 404);
            if ($sourceId === $destinationId || $source->merged_into_id || $destination->merged_into_id) {
                throw ValidationException::withMessages(['destination_id' => 'Choose two different canonical tags.']);
            }
            self::checkVersion($source, $baseVersion);
            self::checkVersion($destination, $destinationVersion);
            if (DB::table('tag_relations')->where('tag_id', $sourceId)->orWhere('related_tag_id', $sourceId)->exists()) {
                throw ValidationException::withMessages(['destination_id' => 'This tag has related or implied relationships. Review and remove those relationships before merging; they are not silently discarded.']);
            }
            $postIds = DB::table('post_tag')->where('tag_id', $sourceId)->orderBy('post_id')->pluck('post_id');
            $revisionIds = [];
            foreach ($postIds as $id) {
                $post = Post::withoutGlobalScope('published')->withTrashed()->lockForUpdate()->findOrFail($id);
                $before = self::snapshot($post);
                $names = array_map(fn ($name) => $name === $source->name ? $destination->name : $name, $before['tags']);
                $version = $post->tag_version;
                Tag::syncFor($post, $names, audit: false);
                $revisionIds[] = KnowledgeRevision::create([
                    'target_type' => 'post', 'target_id' => $post->id,
                    'user_id' => $admin->id, 'reviewer_id' => $admin->id,
                    'before' => $before, 'after' => self::snapshot($post->fresh()),
                    'base_version' => $version, 'summary' => $reason, 'status' => 'accepted',
                ])->id;
            }
            $after = self::snapshot($source);
            $after['merged_into_id'] = $destinationId;
            $revisionIds[] = self::propose($admin, 'tag', $sourceId, $after, $source->version, $reason)->id;
            ModerationAction::record($admin, 'knowledge_merge', 'tag', $sourceId, null, $reason, [
                'destination_id' => $destinationId, 'affected_posts' => $postIds->count(),
                'revision_ids' => $revisionIds,
            ]);
        });
    }

    public static function revert(KnowledgeRevision $revision, User $moderator, string $summary): void
    {
        DB::transaction(function () use ($revision, $moderator, $summary) {
            $revision = KnowledgeRevision::lockForUpdate()->findOrFail($revision->id);
            abort_unless($revision->status === 'accepted', 409, 'Only accepted revisions can be reverted.');
            if (ModerationAction::where('action', 'knowledge_merge')->whereJsonContains('meta->revision_ids', $revision->id)->exists()) {
                throw ValidationException::withMessages(['revision' => 'This revision belongs to a tag merge. Media, redirects, and followers changed together; a single-revision revert is unsafe. Make a coordinated correction instead.']);
            }
            $target = self::target($revision->target_type, $revision->target_id, true);
            $current = $revision->target_type === 'bulk'
                ? ['posts' => collect(array_keys($revision->after['posts']))->mapWithKeys(fn ($id) => [$id => self::snapshot(Post::lockForUpdate()->findOrFail($id))])->all()]
                : self::snapshot($target);
            if ($current != $revision->after) {
                throw ValidationException::withMessages(['revision' => 'Later changes exist. Compare revisions and submit a new correction instead of overwriting them.']);
            }
            // Redirects may be undone by moderators, but remain read-only to contributors.
            if ($target instanceof Tag && $target->merged_into_id) {
                $target->merged_into_id = null;
                $target->save();
            }
            $undo = KnowledgeRevision::create([
                'target_type' => $revision->target_type, 'target_id' => $revision->target_id,
                'user_id' => $moderator->id, 'before' => $current, 'after' => $revision->before,
                'base_version' => self::version($target), 'summary' => $summary,
            ]);
            self::apply($undo, $moderator, true);
            $revision->update(['status' => 'reverted']);
        });
    }

    public static function notify(KnowledgeRevision $revision, string $status): void
    {
        $ids = DB::table('knowledge_watches')->where('target_type', $revision->target_type === 'bulk' ? 'tag' : $revision->target_type)
            ->where('target_id', $revision->target_id)->pluck('user_id')->all();
        if ($revision->user_id) {
            $ids[] = $revision->user_id;
        }
        foreach (array_unique($ids) as $id) {
            if ($id === $revision->reviewer_id) {
                continue;
            }
            AppNotification::create([
                'user_id' => $id, 'actor_id' => $revision->reviewer_id,
                'type' => 'knowledge', 'subject_type' => 'revision', 'subject_id' => $revision->id,
                'data' => ['message' => "Community edit {$status}: ".$revision->summary,
                    'url' => route('knowledge.revision', $revision->id, false)],
            ]);
        }
    }
}
