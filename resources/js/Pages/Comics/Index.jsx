import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { SeriesCard } from '@/Components/Work';
import AdSlot from '@/Components/AdSlot';
import SearchBox from '@/Components/SearchBox';

export default function ComicsIndex({ comics, continueReading = [], searchQuery = '' }) {
    const { auth } = usePage().props;
    return (
        <AppLayout>
            <Head title="Comics" />
            <TopBar title="Comics" search={false} subtitle="Series, newest first" actions={auth.user && <Link href={route('comics.chapters.create')} className="btn-ghost text-xs sm:text-sm">New series</Link>} />
            <div className="p-3.5 md:p-5">
                <SearchBox scope="comics" initial={searchQuery} className="mx-auto mb-4 max-w-xl" />
                <AdSlot className="mb-4 xl:hidden" placement="comics-mobile" compact />
                {auth.user && <Link href={route('chapters.inbox')} className="btn-ghost mb-4">New Chapters</Link>}
                {continueReading.length > 0 && (
                    <section aria-labelledby="continue-reading" className="mb-6">
                        <h2 id="continue-reading" className="mb-3 text-lg font-bold">Continue Reading</h2>
                        <ul className="grid gap-2 sm:grid-cols-2">
                            {continueReading.map((entry) => (
                                <li key={entry.id} className="min-w-0">
                                    <Link href={entry.url} className="panel block break-words hover:bg-ink-raised">
                                        <span className="block font-bold">{entry.title}</span>
                                        <span className="block text-sm text-ink-muted">Chapter {entry.chapter} · Page {entry.page} of {entry.pages}</span>
                                        {entry.hasNewerChapter && <span className="text-xs font-semibold text-ink-brand">Newer chapter available</span>}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                {comics.length === 0 ? (
                    <div className="grid place-items-center gap-3 py-24 text-center">
                        <p className="text-sm text-ink-muted">{searchQuery ? 'No comics match your search.' : 'No series yet.'}</p>
                        <Link href={route('comics.chapters.create')} className="btn-primary">Start a series</Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:gap-4 xl:grid-cols-4">
                        {comics.map((c) => (
                            <SeriesCard key={c.id} comic={c} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
