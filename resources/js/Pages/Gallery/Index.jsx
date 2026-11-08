import { Head, Link, router } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Masonry } from '@/Components/Work';
import GalleryAdSlot from '@/Components/GalleryAdSlot';
import { Icon } from '@/Components/Icons';
import GalleryFilterSheet, { GalleryFilterButton } from '@/Components/GalleryFilterSheet';
import { useEffect, useRef, useState } from 'react';
import { clearedGalleryFilters, galleryFilterChips, patchGalleryFilters } from '@/Lib/galleryFilters';

export default function GalleryIndex({ posts, page = 1, hasMore, filters: serverFilters, stats, sortOptions, windowOptions, formatOptions, tagSuggestions = [] }) {
    const [filters, setFilters] = useState(serverFilters);
    const filtersRef = useRef(serverFilters);
    const visitRef = useRef(0);
    const [busy, setBusy] = useState(false);
    const [items, setItems] = useState(posts);
    const [currentPage, setCurrentPage] = useState(page);
    const [moreAvailable, setMoreAvailable] = useState(hasMore);
    const [loadingMore, setLoadingMore] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const requestRef = useRef(null);
    const [sheetOpen, setSheetOpen] = useState(false);

    useEffect(() => {
        filtersRef.current = serverFilters;
        setFilters(serverFilters);
    }, [serverFilters]);

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
        requestRef.current = null;
        const next = patchGalleryFilters(filtersRef.current, patch);
        const visit = ++visitRef.current;
        filtersRef.current = next;
        setFilters(next);
        setBusy(true);
        router.get(route('gallery'), next, {
            preserveScroll: true, preserveState: true,
            onError: () => {
                if (visit !== visitRef.current) return;
                filtersRef.current = serverFilters;
                setFilters(serverFilters);
            },
            onFinish: () => { if (visit === visitRef.current) setBusy(false); },
        });
    };

    const visibleTags = [...new Set(items.flatMap((post) => post.tags ?? []))].slice(0, 12);

    const chips = galleryFilterChips(filters, formatOptions, windowOptions);
    const activeCount = chips.length;

    const windowLabel = windowOptions.find((w) => w.value === filters.window)?.label ?? 'This week';
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

            {!filters.tag && visibleTags.length > 0 && (
                <nav className="scrollbar-none flex gap-2 overflow-x-auto px-4 pb-3 md:px-5" aria-label="Browse tags">
                    {visibleTags.map((tag) => (
                        <button type="button" key={tag} className="chip" onClick={() => navigate({ tag })}>#{tag}</button>
                    ))}
                </nav>
            )}

            {filters.q ? (
                <div className="flex flex-wrap items-center gap-2 px-4 pb-3 md:px-5">
                    <Link href={route('search', { q: filters.q })} className="text-[13px] font-semibold text-ink-brand hover:underline">
                        See all search results →
                    </Link>
                </div>
            ) : null}

            <div className="flex flex-wrap items-center gap-2 px-4 pb-3 md:px-5">
                <label className="flex items-center gap-1.5 rounded-lg border border-ink-line px-2 text-[13px]">
                    <Icon name="sort" className="h-4 w-4 text-ink-muted" />
                    <select aria-label="Sort by" value={filters.sort} onChange={(e) => navigate({ sort: e.target.value })}
                        className="min-h-10 border-0 bg-ink-bg py-1 pl-0 pr-8 text-[13px] font-semibold focus:ring-ink-brand">
                        {sortOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                </label>
                <GalleryFilterButton onClick={() => setSheetOpen(true)} activeCount={activeCount} expanded={sheetOpen} />
                <span className="text-[12px] text-ink-muted">{windowLabel}</span>
                {filters.sort !== 'top' && <button type="button" onClick={() => navigate({ sort: 'top' })} className="ml-auto text-[12px] font-semibold text-ink-brand hover:underline">Reset sort</button>}
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

            <GalleryFilterSheet
                open={sheetOpen}
                onClose={() => setSheetOpen(false)}
                filters={filters}
                windowOptions={windowOptions}
                formatOptions={formatOptions}
                tagSuggestions={tagSuggestions}
                activeCount={activeCount}
                onApply={(next) => navigate(next)}
            />

            {activeCount > 0 && <div className="flex flex-wrap items-center gap-2 px-4 pt-3 text-[12.5px] md:px-5" aria-label="Active filters">
                {chips.map((chip) => <button type="button" key={chip.key} onClick={() => navigate(chip.patch)} className="chip is-active" aria-label={`Remove filter: ${chip.label}`}>
                    {chip.label} <Icon name="close" className="h-3 w-3 shrink-0" />
                </button>)}
                <button type="button" onClick={() => navigate(clearedGalleryFilters)} className="ml-auto text-[12px] font-semibold text-ink-brand hover:underline">Clear filters</button>
            </div>}

            <div className="px-2.5 pb-16 pt-2 md:px-5 md:pt-3">
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
