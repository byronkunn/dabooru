import { Head, Link } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';

const COPY = {
    403: ['Not allowed', 'You do not have access to this page. If you expected to, check that you are signed in to the right account.'],
    404: ['Page not found', 'That link may have moved, been removed, or never existed. Work can also disappear when its artist deletes it or restricts who can see it.'],
    419: ['Session expired', 'Your session timed out while you were away. Go back and try the action again.'],
    429: ['Slow down', 'Too many requests in a short time. Wait a moment and try again.'],
    500: ['Something broke', 'The problem has been recorded. Try again in a minute; if it keeps happening, let us know.'],
    503: ['Down for maintenance', 'BooruHub is being updated and will be back shortly.'],
};

export default function Error({ status }) {
    const [title, description] = COPY[status] ?? ['Something went wrong', 'An unexpected error occurred. Please try again.'];

    return (
        <AppLayout rightRail={false}>
            <Head title={title} />
            <TopBar title={title} search={false} />
            <section className="mx-auto max-w-[560px] px-5 py-14 text-center">
                <p className="text-[64px] font-black leading-none tracking-tight text-ink-brand">{status}</p>
                <h2 className="mt-4 text-xl font-bold">{title}</h2>
                <p className="mt-3 text-[15px] leading-relaxed text-ink-muted">{description}</p>
                <div className="mt-8 flex flex-wrap justify-center gap-2">
                    <Link href={route('gallery')} className="btn-primary">Back to the gallery</Link>
                    <button type="button" className="btn-ghost" onClick={() => window.history.back()}>Go back</button>
                </div>
            </section>
        </AppLayout>
    );
}
