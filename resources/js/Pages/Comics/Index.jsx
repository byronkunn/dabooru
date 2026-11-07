import { Head, Link } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { SeriesCard } from '@/Components/Work';

export default function ComicsIndex({ comics }) {
    return (
        <AppLayout>
            <Head title="Comics" />
            <TopBar title="Comics" subtitle="Series, newest first" actions={<Link href={route('comics.chapters.create')} className="btn-primary hidden md:inline-flex">New series</Link>} />
            <div className="p-3.5 md:p-5">
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
