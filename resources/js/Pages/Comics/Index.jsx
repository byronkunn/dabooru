import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { SeriesCard } from '@/Components/Work';

export default function ComicsIndex({ comics, continueReading = [] }) {
    const { auth } = usePage().props;
    return (
        <AppLayout>
            <Head title="Comics" />
            <TopBar title="Comics" subtitle="Series, newest first" actions={<Link href={route('comics.chapters.create')} className="btn-primary hidden md:inline-flex">New series</Link>} />
            <div className="p-3.5 md:p-5">
                {auth.user && <Link href={route('chapters.inbox')} className="btn-ghost mb-4">Chapter inbox</Link>}
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
                        <p className="text-sm text-ink-muted">No series yet.</p>
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
