import { Head, Link } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Masonry } from '@/Components/Work';

export default function ArtistShow({ artist, posts, nextPage, previousPage }) {
    return (
        <AppLayout>
            <Head title={`${artist.name} · Artist`} />
            <TopBar title="Artist" back={route('artists.index')} />
            <header className="space-y-3 border-b border-ink-line p-4 md:p-5">
                <p className="text-xs font-bold uppercase tracking-wider text-amber-300">Artist credit page · Not a member profile</p>
                <h1 className="break-words text-2xl font-bold">{artist.name}</h1>
                <p className="text-sm leading-relaxed text-ink-muted">Artwork credited to this artist and shared by community members. This page does not mean the artist has joined ModernBooru, manages this page, or endorses the uploads.</p>
                <p className="text-xs text-ink-muted">Credits are grouped by the supplied artist name, not a verified identity. Original sources and uploaders are listed on each work.</p>
                <p className="text-sm font-semibold">{artist.works} {artist.works === 1 ? 'work' : 'works'} on ModernBooru</p>
                <div className="flex flex-wrap gap-3 text-xs">
                    <Link href={route('artists.index')} className="link-brand">Browse artists</Link>
                    <Link href={route('dmca')} className="link-brand">Request removal / copyright help</Link>
                </div>
            </header>
            <div className="p-3 md:p-5"><Masonry posts={posts} /></div>
            <nav aria-label="Artist works pages" className="flex justify-between gap-3 p-4">
                {previousPage && <Link href={previousPage} className="btn-ghost">Previous</Link>}
                {nextPage && <Link href={nextPage} className="btn-ghost ml-auto">Next</Link>}
            </nav>
        </AppLayout>
    );
}
