import { Head, Link } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { WorkCell } from '@/Components/Work';
import { AdminPagination } from '@/Components/AdminUi';

export default function Shelves({ shelves, nextPage, previousPage }) {
    return (
        <AppLayout>
            <Head title="Discover" />
            <TopBar title="Discover" subtitle="Selected by Inkwell curators" />
            <div className="space-y-6 p-3.5 md:p-5">
                {shelves.map((shelf) => <section key={shelf.id} className="space-y-3">
                    <div>
                        <h2 className="break-words text-lg font-bold">{shelf.title}</h2>
                        {shelf.description && <p className="break-words text-sm text-ink-muted">{shelf.description}</p>}
                    </div>
                    <div className="grid grid-cols-2 items-start gap-3 md:grid-cols-3">
                        {shelf.entries.map((entry) => entry.kind === 'work' ? <WorkCell key={`work-${entry.id}`} post={entry.post} />
                            : <Link key={`${entry.kind}-${entry.id}`} href={entry.url} className="panel min-w-0 hover:bg-ink-raised">
                                {entry.image && <img src={entry.image} alt="" loading="lazy" className={entry.kind === 'artist' ? 'mb-2 h-12 w-12 rounded-full object-cover' : 'mb-2 aspect-[3/4] w-full rounded-lg object-cover'} />}
                                <p className="text-xs font-semibold text-ink-muted">{entry.kind === 'artist' ? 'Artist spotlight' : 'Comic series'}</p>
                                <p className="break-words font-bold">{entry.title}</p>
                            </Link>)}
                    </div>
                </section>)}
                {shelves.length === 0 && <p className="py-12 text-center text-sm text-ink-muted">No curated shelves to show here yet.</p>}
                <AdminPagination {...{ previousPage, nextPage }} />
            </div>
        </AppLayout>
    );
}
