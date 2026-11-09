import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';

export default function ArtistIndex({ artists, search, nextPage, previousPage }) {
    const [query, setQuery] = useState(search);
    return (
        <AppLayout>
            <Head title="Artist directory" />
            <TopBar title="Artist directory" back={route('gallery')} />
            <div className="space-y-4 p-4 md:p-5">
                <p className="text-sm text-ink-muted">Browse credited artists whose work is shared here. These are artwork credit pages, not member accounts.</p>
                <form className="flex gap-2" onSubmit={(event) => {
                    event.preventDefault();
                    router.get(route('artists.index'), { q: query.trim() || undefined });
                }}>
                    <input type="search" aria-label="Search artist credits" placeholder="Artist name or handle" maxLength={120}
                        value={query} onChange={(event) => setQuery(event.target.value)} className="field-input min-w-0 flex-1" />
                    <button className="btn-primary" type="submit">Search</button>
                </form>
                {!artists.length && <p className="py-8 text-center text-sm text-ink-muted">No artist credits found.</p>}
                <div className="grid gap-3 sm:grid-cols-2">
                    {artists.map((artist) => <Link key={artist.name} href={artist.url} className="min-w-0 rounded-xl border border-ink-line p-4 hover:bg-ink-raised">
                        <h2 className="break-words font-bold">{artist.name}</h2>
                        <p className="mt-1 text-xs text-ink-muted">Artist credit page · {artist.works} {artist.works === 1 ? 'work' : 'works'}</p>
                    </Link>)}
                </div>
                <nav aria-label="Artist directory pages" className="flex justify-between gap-3">
                    {previousPage && <Link href={previousPage} className="btn-ghost">Previous</Link>}
                    {nextPage && <Link href={nextPage} className="btn-ghost ml-auto">Next</Link>}
                </nav>
            </div>
        </AppLayout>
    );
}
