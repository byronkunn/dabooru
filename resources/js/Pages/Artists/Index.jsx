import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';

export default function ArtistIndex({ artists, search, nextPage, previousPage, popularArtists = [] }) {
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
                {!artists.length && (search ? <section className="space-y-4 rounded-xl border border-ink-line p-4 text-center">
                    <div><h2 className="font-bold">No credits match “{search}”</h2><p className="mt-1 text-sm text-ink-muted">Try another spelling, or browse credited artists with the most works.</p></div>
                    <Link href={route('artists.index')} className="btn-ghost">Clear search</Link>
                    {popularArtists.length > 0 && <div className="text-left"><h3 className="mb-2 text-sm font-bold">Popular artist credits</h3><div className="grid gap-2 sm:grid-cols-2">{popularArtists.map((artist) => <Link key={artist.name} href={artist.url} className="rounded-lg border border-ink-line p-3 hover:bg-ink-raised"><span className="block break-words font-semibold">{artist.name}</span><span className="text-xs text-ink-muted">{artist.works} credited works</span></Link>)}</div></div>}
                </section> : <section className="rounded-xl border border-ink-line p-4 text-center">
                    <h2 className="font-bold">No artist credits yet</h2><p className="mt-1 text-sm text-ink-muted">Artist pages appear when artwork is uploaded with an artist credit. You can still explore the gallery.</p>
                    <Link href={route('gallery')} className="btn-ghost mt-3 inline-flex">Browse the gallery</Link>
                </section>)}
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
