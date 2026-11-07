import { Head, Link, router } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { AdminPagination } from '@/Components/AdminUi';

export default function ChapterInbox({ chapters, nextPage, previousPage }) {
    return (
        <AppLayout>
            <Head title="Chapter inbox" />
            <TopBar title="Chapter inbox" back={route('comics.index')} />
            <div className="space-y-3 p-3.5 md:p-5">
                {chapters.length ? <ul className="space-y-2">
                    {chapters.map((chapter) => <li key={chapter.id} className="panel space-y-3">
                        <div>
                            <p className="text-xs text-ink-muted">{chapter.series} · {chapter.published}</p>
                            <h2 className="break-words font-bold">Chapter {chapter.number}{chapter.title && ` · ${chapter.title}`}</h2>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link href={chapter.url} className="btn-primary">Read chapter</Link>
                            {chapter.resume && <Link href={chapter.resume} className="btn-ghost">Resume earlier chapter</Link>}
                            <button type="button" className="btn-ghost" onClick={() => router.post(route('chapters.mark-read', chapter.id), {}, { preserveScroll: true })}>Mark read</button>
                        </div>
                    </li>)}
                </ul> : <p className="py-12 text-center text-sm text-ink-muted">You’re caught up. Follow a comic series to see unread chapters here.</p>}
                <AdminPagination {...{ previousPage, nextPage }} />
            </div>
        </AppLayout>
    );
}
