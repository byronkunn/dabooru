import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Masonry } from '@/Components/Work';
import GalleryAdSlot from '@/Components/GalleryAdSlot';
import { useEffect, useRef, useState } from 'react';

export default function GalleryIndex({ posts, page = 1, hasMore, filters, stats, sortOptions, windowOptions, formatOptions }) {
    const { auth } = usePage().props;
    const [busy, setBusy] = useState(false);
    const [items, setItems] = useState(posts);
    const [currentPage, setCurrentPage] = useState(page);
    const [moreAvailable, setMoreAvailable] = useState(hasMore);
    const [loadingMore, setLoadingMore] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const requestRef = useRef(null);

    useEffect(() => {
        setItems(posts);
        setCurrentPage(page);
        setMoreAvailable(hasMore);
        setLoadingMore(false);
        setLoadError(false);
        return () => requestRef.current?.abort();
    }, [posts, page, hasMore]);

    const loadMore = async () => {
        if (busy || requestRef.current || !moreAvailable) return;
        const request = new AbortController();
        requestRef.current = request;
        setLoadingMore(true);
        setLoadError(false);
        try {
            const response = await window.axios.get(route('gallery'), {
                params: { ...filters, page: currentPage + 1 },
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            if (request.signal.aborted) return;
            setItems((existing) => {
                const loaded = new Set(existing.map((post) => post.id));
                return [...existing, ...response.data.posts.filter((post) => !loaded.has(post.id))];
            });
            setCurrentPage(response.data.page);
            setMoreAvailable(response.data.hasMore);
        } catch {
            if (!request.signal.aborted) setLoadError(true);
        } finally {
            if (requestRef.current === request) {
                requestRef.current = null;
                setLoadingMore(false);
            }
        }
    };

    const navigate = (patch) => {
        requestRef.current?.abort();
        const next = { ...filters, ...patch };
        router.get(route('gallery'), next, { preserveScroll: true, preserveState: true, replace: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    };

    const visibleTags = [...new Set(items.flatMap((post) => post.tags ?? []))].slice(0, 12);
    const tabs = (
        <>
            <div className="scrollbar-none mt-3.5 flex gap-1.5 overflow-x-auto px-4 pb-2.5 md:px-5" role="tablist" aria-label="Format">
                {formatOptions.map((opt) => (
                    <button
                        key={opt.value}
                        type="button"
                        role="tab"
                        aria-selected={filters.format === opt.value}
                        onClick={() => navigate({ format: opt.value })}
                        className="chip"
                    >
                        {opt.label}
                    </button>
                ))}
            </div>

            {filters.format === 'comic' && (
                <Link href={route('comics.index')} className="link-brand mx-4 mb-3 inline-block text-sm font-semibold md:mx-5">
                    Browse comic series
                </Link>
            )}

            {(filters.tag || visibleTags.length > 0) && (
                <nav className="scrollbar-none flex gap-2 overflow-x-auto px-4 pb-3 md:px-5" aria-label="Browse tags">
                    {filters.tag ? (
                        <button type="button" className="chip is-active" onClick={() => navigate({ tag: '' })}>
                            #{filters.tag} <span aria-label="Clear tag">×</span>
                        </button>
                    ) : visibleTags.map((tag) => (
                        <button type="button" key={tag} className="chip" onClick={() => navigate({ tag, window: 'all' })}>#{tag}</button>
                    ))}
                </nav>
            )}

            {filters.q ? (
                <div className="flex flex-wrap items-center gap-2 px-4 pb-3 md:px-5">
                    <span className="chip is-active">“{filters.q}”</span>
                    <button type="button" onClick={() => navigate({ q: '' })} className="chip">
                        Clear search ✕
                    </button>
                    <Link href={route('search', { q: filters.q })} className="text-[13px] font-semibold text-ink-brand hover:underline">
                        See all search results →
                    </Link>
                </div>
            ) : null}

            <div className="scrollbar-none flex items-center gap-2 overflow-x-auto px-4 pb-3 md:px-5" role="group" aria-label="Sort and filters">
                <div className="flex shrink-0 rounded-full border border-ink-line bg-ink-raised p-[3px]">
                    {sortOptions.map((opt) => (
                        <button
                            key={opt.value}
                            type="button"
                            aria-pressed={filters.sort === opt.value}
                            onClick={() => navigate({ sort: opt.value })}
                            className={`min-h-9 whitespace-nowrap rounded-full px-3 text-[13px] font-semibold transition-colors ${
                                filters.sort === opt.value ? 'bg-ink-brand text-[#1c1017]' : 'text-ink-muted hover:text-ink-text'
                            }`}
                        >
                            {opt.label}
                        </button>
                    ))}
                </div>

                <button
                    type="button"
                    onClick={() => {
                        const idx = windowOptions.findIndex((w) => w.value === filters.window);
                        navigate({ window: windowOptions[(idx + 1) % windowOptions.length].value });
                    }}
                    className="chip shrink-0"
                    aria-label="Change time window"
                >
                    {windowOptions.find((w) => w.value === filters.window)?.label}
                    <span className="opacity-60">▾</span>
                </button>

                <div className="flex shrink-0 items-center gap-2 text-[13px] text-ink-muted">
                    <button
                        type="button"
                        role="switch"
                        aria-checked={filters.explicit}
                        aria-label="Show explicit"
                        onClick={() => (auth.user ? navigate({ explicit: !filters.explicit }) : router.post(route('age-gate.confirm')))}
                        className={`relative h-5 w-9 shrink-0 rounded-full transition-colors ${filters.explicit ? 'bg-ink-brand/40' : 'bg-[#3a3242]'}`}
                    >
                        <span
                            className={`absolute top-0.5 h-4 w-4 rounded-full transition-all ${
                                filters.explicit ? 'left-[18px] bg-ink-brand' : 'left-0.5 bg-[#8d8297]'
                            }`}
                        />
                    </button>
                    <span className="hidden sm:inline">Show explicit</span>
                </div>
            </div>
        </>
    );

    return (
        <AppLayout>
            <Head title="Gallery" />
            <TopBar
                title="Gallery"
                subtitle={`${stats.works.toLocaleString()} works · ${stats.artists.toLocaleString()} artists`}
                tabs={tabs}
            />

            <div className="px-2.5 pb-16 pt-3 md:px-5 md:pt-4">
                {items.length === 0 ? (
                    <div className="grid place-items-center gap-3 py-24 text-center text-ink-muted">
                        <p className="text-[15px]">Nothing in this filter yet.</p>
                        <Link href={route('upload.create')} className="btn-primary">
                            Be the first to post
                        </Link>
                    </div>
                ) : (
                    <Masonry posts={items} renderAfterPost={(_, index) => (
                        (index + 1) % 4 === 0 ? <GalleryAdSlot afterWork={index + 1} /> : null
                    )} />
                )}
                {moreAvailable && (
                    <div className="grid place-items-center py-8">
                        <button type="button" onClick={loadMore} disabled={loadingMore || busy} className="btn-ghost" aria-busy={loadingMore}>
                            {loadingMore ? 'Loading…' : `Load more works (${items.length} shown)`}
                        </button>
                        {loadError && <p role="status" className="mt-2 text-sm text-ink-muted">Could not load more works. Try again.</p>}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
