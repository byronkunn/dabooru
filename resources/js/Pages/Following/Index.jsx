import { Head, Link, router, usePage, useRemember } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { PostCard } from '@/Components/Work';
import { useEffect, useRef, useState } from 'react';
import TagFollowButton from '@/Components/TagFollowButton';
import UserFollowButton from '@/Components/UserFollowButton';
import { AdminPagination } from '@/Components/AdminUi';
import FeedTabs from '@/Components/FeedTabs';

export default function FollowingIndex({ posts, nextCursor, followingCount, followingUsers }) {
    const { props: { auth }, url } = usePage();
    const [loaded, setLoaded] = useRemember(null, 'following-scroll');
    const restored = useRef(loaded?.url === url && loaded?.first === posts[0]?.id).current;
    const [feed, setFeed] = useState(restored ? loaded.posts : posts);
    const [cursor, setCursor] = useState(restored ? loaded.cursor : nextCursor);
    const currentProps = useRef({ posts, nextCursor });
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const sentinel = useRef(null);

    useEffect(() => {
        if (restored && currentProps.current.posts === posts && currentProps.current.nextCursor === nextCursor) return;
        currentProps.current = { posts, nextCursor };
        setFeed(posts);
        setCursor(nextCursor);
        setLoadError(false);
    }, [posts, nextCursor, restored]);

    useEffect(() => {
        setLoaded(feed.length > posts.length ? { url, first: posts[0]?.id, posts: feed, cursor } : null);
    }, [feed, cursor, url, posts]);

    useEffect(() => {
        if (!sentinel.current || !cursor || loadError) return;
        const io = new IntersectionObserver((entries) => {
            if (!entries[0].isIntersecting || loading) return;
            setLoading(true);
            fetch(`${route('following')}?cursor=${encodeURIComponent(cursor)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((r) => {
                    if (!r.ok) throw new Error('Unable to load the Following feed.');
                    return r.json();
                })
                .then((data) => {
                    const incoming = data.props?.posts ?? data.posts ?? [];
                    setFeed((f) => [...f, ...incoming]);
                    setCursor(data.props?.nextCursor ?? data.nextCursor ?? null);
                })
                .catch(() => setLoadError(true))
                .finally(() => setLoading(false));
        }, { rootMargin: '600px' });
        io.observe(sentinel.current);
        return () => io.disconnect();
    }, [cursor, loading, loadError]);

    return (
        <AppLayout>
            <Head title="Following" />
            <TopBar
                title="Following"
                tabs={<FeedTabs />}
            />

            <div className="mx-auto max-w-[680px]">
                <details className="m-3 rounded-xl border border-ink-line p-3">
                    <summary className="cursor-pointer py-2 font-semibold">Manage following · {followingCount} users · {(auth.followed_tags ?? []).length} tags</summary>
                    <h2 className="mt-3 font-semibold">Followed tags</h2>
                    <div className="mt-2 space-y-2">
                        {(auth.followed_tags ?? []).map((tag) => <div key={tag.id} className="flex flex-wrap items-center justify-between gap-2">
                            <Link href={route('gallery', { tag: tag.name, window: 'all' })} className="min-w-0 break-words text-ink-brand">#{tag.name}</Link>
                            <TagFollowButton tag={tag.name} />
                        </div>)}
                        {!auth.followed_tags?.length && <p className="text-sm text-ink-muted">Open a tag in the gallery or on a work to follow it.</p>}
                    </div>
                    <h2 className="mt-4 font-semibold">Followed users</h2>
                    <div className="mt-2 space-y-2">
                        {followingUsers.data.map((user) => <div key={user.id} className="flex flex-wrap items-center justify-between gap-2">
                            <Link href={route('profile.show', user.username)} className="min-w-0 break-all text-ink-brand">@{user.username}</Link>
                            <UserFollowButton username={user.username} following />
                        </div>)}
                        {!followingCount && <p className="text-sm text-ink-muted">You are not following any users yet.</p>}
                    </div>
                    <AdminPagination previousPage={followingUsers.prev_page_url} nextPage={followingUsers.next_page_url} />
                </details>
                {feed.length === 0 ? (
                    <div className="grid place-items-center gap-3 px-6 py-24 text-center">
                        <p className="text-[15px] text-ink-muted">
                            No visible work from your followed users or tags yet.
                        </p>
                        <Link href={route('gallery')} className="btn-primary">
                            Find artists in the gallery
                        </Link>
                    </div>
                ) : (
                    feed.map((post) => <PostCard key={post.id} post={post} liked={post.liked} saved={post.saved} viewerId={auth.user?.id} />)
                )}
                <div ref={sentinel} className="h-px" />
                {loading && <p className="py-6 text-center text-sm text-ink-muted">Loading more…</p>}
                {loadError && <div role="alert" className="p-4 text-center text-sm text-red-300">Could not load more work. <button type="button" className="underline" onClick={() => setLoadError(false)}>Retry</button></div>}
                {!cursor && feed.length > 0 && <p className="py-8 text-center text-sm text-ink-muted">You&apos;re all caught up.</p>}
            </div>
        </AppLayout>
    );
}
