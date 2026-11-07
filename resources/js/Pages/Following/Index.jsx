import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { PostCard } from '@/Components/Work';
import { useEffect, useRef, useState } from 'react';

export default function FollowingIndex({ posts, nextCursor, followingCount }) {
    const { auth } = usePage().props;
    const [feed, setFeed] = useState(posts);
    const [cursor, setCursor] = useState(nextCursor);
    const [loading, setLoading] = useState(false);
    const sentinel = useRef(null);

    useEffect(() => {
        setFeed(posts);
        setCursor(nextCursor);
    }, [posts, nextCursor]);

    useEffect(() => {
        if (!sentinel.current || !cursor) return;
        const io = new IntersectionObserver((entries) => {
            if (!entries[0].isIntersecting || loading) return;
            setLoading(true);
            fetch(`${route('following')}?cursor=${encodeURIComponent(cursor)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((r) => r.json())
                .then((data) => {
                    const incoming = data.props?.posts ?? data.posts ?? [];
                    setFeed((f) => [...f, ...incoming]);
                    setCursor(data.props?.nextCursor ?? data.nextCursor ?? null);
                })
                .finally(() => setLoading(false));
        }, { rootMargin: '600px' });
        io.observe(sentinel.current);
        return () => io.disconnect();
    }, [cursor, loading]);

    return (
        <AppLayout>
            <Head title="Following" />
            <TopBar
                title="Following"
                tabs={
                    <div className="flex border-t border-ink-line">
                        <span className="flex-1 border-b-2 border-ink-brand py-3.5 text-center text-[14.5px] font-semibold">Following</span>
                        <Link href={route('gallery')} className="flex-1 py-3.5 text-center text-[14.5px] font-semibold text-ink-muted hover:text-ink-text">
                            Discover
                        </Link>
                    </div>
                }
            />

            <div className="mx-auto max-w-[680px]">
                {feed.length === 0 ? (
                    <div className="grid place-items-center gap-3 px-6 py-24 text-center">
                        <p className="text-[15px] text-ink-muted">
                            You follow {followingCount} {followingCount === 1 ? 'artist' : 'artists'} but nothing is here yet.
                        </p>
                        <Link href={route('gallery')} className="btn-primary">
                            Find artists in the gallery
                        </Link>
                    </div>
                ) : (
                    feed.map((post) => <PostCard key={post.id} post={post} />)
                )}
                <div ref={sentinel} className="h-px" />
                {loading && <p className="py-6 text-center text-sm text-ink-muted">Loading more…</p>}
                {!cursor && feed.length > 0 && <p className="py-8 text-center text-sm text-ink-muted">You&apos;re all caught up.</p>}
            </div>
        </AppLayout>
    );
}
