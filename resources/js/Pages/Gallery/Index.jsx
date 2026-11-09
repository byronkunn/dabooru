import { Head, Link, router, useForm, usePage, useRemember } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Masonry } from '@/Components/Work';
import GalleryAdSlot from '@/Components/GalleryAdSlot';
import TagFollowButton from '@/Components/TagFollowButton';
import FeedTabs from '@/Components/FeedTabs';
import { Icon } from '@/Components/Icons';
import GalleryFilterSheet, { GalleryFilterButton } from '@/Components/GalleryFilterSheet';
import { useEffect, useRef, useState } from 'react';
import { clearedGalleryFilters, galleryFilterChips, patchGalleryFilters } from '@/Lib/galleryFilters';
import SearchBox from '@/Components/SearchBox';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { AdminErrors } from '@/Components/AdminUi';
import CollectionDialog from '@/Components/CollectionDialog';

export default function GalleryIndex({ posts, page = 1, hasMore, filters: serverFilters, sortOptions, windowOptions, formatOptions, tagSuggestions = [], searchTags = { included: [], excluded: [] } }) {
    const [filters, setFilters] = useState(serverFilters);
    const filtersRef = useRef(serverFilters);
    const visitRef = useRef(0);
    const [busy, setBusy] = useState(false);
    const { url, props: { auth } } = usePage();
    const [loaded, setLoaded] = useRemember(null, 'gallery-scroll');
    const restored = useRef(loaded?.url === url && loaded?.first === posts[0]?.id).current;
    const [items, setItems] = useState(restored ? loaded.items : posts);
    const [currentPage, setCurrentPage] = useState(restored ? loaded.page : page);
    const [moreAvailable, setMoreAvailable] = useState(restored ? loaded.hasMore : hasMore);
    const currentProps = useRef(posts);
    const [loadingMore, setLoadingMore] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const requestRef = useRef(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [query, setQuery] = useState(serverFilters.q ?? '');
    const [searchDetailsOpen, setSearchDetailsOpen] = useState(false);
    const [savingSearch, setSavingSearch] = useState(false);
    const saveSearch = useForm({ name: '' });

    useEffect(() => setQuery(serverFilters.q ?? ''), [serverFilters.q]);

    useEffect(() => {
        filtersRef.current = serverFilters;
        setFilters(serverFilters);
    }, [serverFilters]);

    useEffect(() => {
        if (restored && currentProps.current === posts) return;
        currentProps.current = posts;
        setItems(posts);
        setCurrentPage(page);
        setMoreAvailable(hasMore);
        setLoadingMore(false);
        setLoadError(false);
        return () => requestRef.current?.abort();
    }, [posts, page, hasMore, restored]);

    useEffect(() => {
        setLoaded(currentPage > page ? { url, first: posts[0]?.id, items, page: currentPage, hasMore: moreAvailable } : null);
    }, [url, items, currentPage, moreAvailable, posts, page]);

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

    const chips = galleryFilterChips(filters, formatOptions, windowOptions);
    const activeCount = chips.length;

    const windowLabel = windowOptions.find((w) => w.value === filters.window)?.label ?? 'This week';
    const tabs = (
        <>
            <div className="mx-auto w-full max-w-[640px] px-3 pb-2 pt-2 md:px-5 md:pb-3 md:pt-3">
                <SearchBox value={query} onChange={setQuery} onSearch={(q) => navigate({ q })} />
            </div>
            <FeedTabs />
            <div
                className="scrollbar-none mt-2 flex flex-nowrap items-center gap-1.5 overflow-x-auto px-3 pb-2 md:mt-3.5 md:flex-wrap md:justify-center md:px-5 md:pb-2.5"
                role="tablist"
                aria-label="Format"
            >
                {formatOptions.map((opt) => (
                    <button
                        key={opt.value}
                        type="button"
                        role="tab"
                        aria-selected={filters.format === opt.value}
                        onClick={() => navigate({ format: opt.value })}
                        className="chip shrink-0 whitespace-nowrap"
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

            <div className="scrollbar-none flex flex-nowrap items-center gap-2 overflow-x-auto px-3 pb-2 md:flex-wrap md:px-5 md:pb-3">
                <label className="flex shrink-0 items-center gap-1.5 rounded-lg border border-ink-line px-2 text-[13px]">
                    <Icon name="sort" className="h-4 w-4 text-ink-muted" />
                    <select aria-label="Sort by" value={filters.sort} onChange={(e) => navigate({ sort: e.target.value })}
                        className="min-h-10 border-0 bg-ink-bg py-1 pl-0 pr-8 text-[13px] font-semibold focus:ring-ink-brand">
                        {sortOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                </label>
                <GalleryFilterButton onClick={() => setSheetOpen(true)} activeCount={activeCount} expanded={sheetOpen} />
                <span className="hidden shrink-0 text-[12px] text-ink-muted sm:inline">{windowLabel}</span>
                {filters.sort !== 'top' && <button type="button" onClick={() => navigate({ sort: 'top' })} className="ml-auto text-[12px] font-semibold text-ink-brand hover:underline">Reset sort</button>}
            </div>
        </>
    );

    return (
        <AppLayout>
            <Head title="Gallery" />
            <TopBar
                title="Gallery"
                search={false}
                hideOnScroll
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

            {filters.tag && <div className="px-4 pt-3 md:px-5"><TagFollowButton tag={filters.tag} /></div>}

            {activeCount > 0 && <div className="flex flex-wrap items-center gap-2 px-4 pt-3 text-[12.5px] md:px-5" aria-label="Active filters">
                {chips.map((chip) => chip.key === 'q' ? <div key={chip.key} className="inline-flex h-10 w-[152px] shrink-0 items-center overflow-hidden rounded-full border border-ink-brand bg-ink-brand text-[#1c1017]">
                    <button type="button" onClick={() => setSearchDetailsOpen(true)} aria-haspopup="dialog" aria-label="View searched tags"
                        className="h-full min-w-0 flex-1 px-3 text-sm font-semibold">Search details</button>
                    <button type="button" onClick={() => navigate(chip.patch)} aria-label="Clear search" className="grid h-full w-9 shrink-0 place-items-center">
                        <Icon name="close" className="h-3 w-3" />
                    </button>
                </div> : <button type="button" key={chip.key} onClick={() => navigate(chip.patch)} className="chip is-active" aria-label={`Remove filter: ${chip.label}`}>
                    {chip.label} <Icon name="close" className="h-3 w-3 shrink-0" />
                </button>)}
                {auth.user && <button type="button" className="btn-ghost" disabled={busy} onClick={() => setSavingSearch(true)}>Save search</button>}
                <button type="button" onClick={() => navigate(clearedGalleryFilters)} className="ml-auto text-[12px] font-semibold text-ink-brand hover:underline">Clear filters</button>
            </div>}

            {savingSearch && <CollectionDialog title="Save search" onClose={() => { if (!saveSearch.processing) setSavingSearch(false); }}>
                <form className="space-y-3" onSubmit={(event) => {
                    event.preventDefault();
                    saveSearch.transform((data) => ({ ...data, gallery: serverFilters }));
                    saveSearch.post(route('saved-searches.store'), { onSuccess: () => { saveSearch.reset(); setSavingSearch(false); } });
                }}>
                    <label className="block field-label">Search name
                        <input className="field-input mt-1" required maxLength={80} value={saveSearch.data.name}
                            onChange={(event) => saveSearch.setData('name', event.target.value)} />
                    </label>
                    <p className="text-sm text-ink-muted">Save these filters to Library. Using an existing name updates that saved search.</p>
                    <AdminErrors errors={saveSearch.errors} />
                    <button className="btn-primary" disabled={saveSearch.processing}>Save search</button>
                </form>
            </CollectionDialog>}

            <Dialog open={searchDetailsOpen} onClose={() => setSearchDetailsOpen(false)} className="relative z-[80]">
                <div className="fixed inset-0 bg-black/70" aria-hidden="true" />
                <div className="fixed inset-0 grid place-items-center overflow-y-auto p-4">
                    <DialogPanel onKeyDown={(event) => { if (event.key === 'Escape') setSearchDetailsOpen(false); }}
                        className="max-h-[85vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-2xl border border-ink-line bg-ink-surface p-5">
                        <div className="flex items-center justify-between gap-3">
                            <DialogTitle className="font-bold">Searched tags</DialogTitle>
                            <button type="button" onClick={() => setSearchDetailsOpen(false)} aria-label="Close search details" className="btn-icon-flat">
                                <Icon name="close" className="h-5 w-5" />
                            </button>
                        </div>
                        {searchTags.included.length > 0 && <section aria-label="Included search tags" className="space-y-2">
                            <h3 className="text-sm font-semibold">Included tags ({searchTags.included.length})</h3>
                            <div className="flex flex-wrap gap-2">{searchTags.included.map((tag) => <span key={tag} className="chip max-w-full !whitespace-normal break-words">#{tag}</span>)}</div>
                        </section>}
                        {searchTags.excluded.length > 0 && <section aria-label="Excluded search tags" className="space-y-2">
                            <h3 className="text-sm font-semibold">Excluded tags ({searchTags.excluded.length})</h3>
                            <div className="flex flex-wrap gap-2">{searchTags.excluded.map((tag) => <span key={tag} className="chip max-w-full !whitespace-normal break-words">−#{tag}</span>)}</div>
                        </section>}
                        {!searchTags.included.length && !searchTags.excluded.length && <p className="text-sm text-ink-muted">No tags in this search.</p>}
                        <section className="space-y-2">
                            <h3 className="text-sm font-semibold">Full search query</h3>
                            <p className="whitespace-pre-wrap break-words text-sm text-ink-muted">{serverFilters.q}</p>
                        </section>
                    </DialogPanel>
                </div>
            </Dialog>

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
