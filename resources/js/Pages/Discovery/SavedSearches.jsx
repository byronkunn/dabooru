import { LibraryTabs } from '@/Components/DestinationTabs';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Masonry } from '@/Components/Work';
import { AdminPagination } from '@/Components/AdminUi';
import LibraryMutedTags from '@/Components/LibraryMutedTags';

export default function SavedSearches({ searches, selected, searchUrl, mutedTags, posts, nextPage, previousPage }) {
    return (
        <AppLayout rightRail={false}>
            <Head title={selected?.name ?? 'Saved searches'} />
            <TopBar title="Saved searches" tabs={<LibraryTabs />} />
            <div className="space-y-4 p-3.5 md:p-5">
                <nav className="flex flex-wrap gap-2" aria-label="Saved searches">
                    {searches.map((search) => <Link key={search.id} href={route('saved-searches.show', search.id)}
                        className={selected?.id === search.id ? 'chip is-active max-w-full truncate' : 'chip max-w-full truncate'}>{search.name}</Link>)}
                    <Link href={route('search')} className="chip">New search</Link>
                </nav>
                {selected ? <>
                    <div className="panel flex flex-wrap items-center justify-between gap-3">
                        <div className="min-w-0">
                            <h2 className="break-words font-bold">{selected.name}</h2>
                            <p className="break-all text-xs text-ink-muted">{selected.filters.q}</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link href={searchUrl} className="btn-ghost">Open search</Link>
                            <button type="button" className="btn-ghost" onClick={() => router.delete(route('saved-searches.destroy', selected.id))}>Unfollow search</button>
                        </div>
                    </div>
                    <LibraryMutedTags tags={mutedTags} />
                    {posts.length > 0 ? <Masonry posts={posts} /> : <p className="py-12 text-center text-sm text-ink-muted">No visible works match this search yet.</p>}
                    <AdminPagination {...{ previousPage, nextPage }} />
                </> : <p className="py-12 text-center text-sm text-ink-muted">Save a search from its results page to find matching works here.</p>}
            </div>
        </AppLayout>
    );
}
