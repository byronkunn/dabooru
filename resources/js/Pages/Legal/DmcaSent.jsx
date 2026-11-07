import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function DmcaSent() {
    return (
        <AppLayout rightRail={false}>
            <Head title="Notice received" />
            <div className="mx-auto grid max-w-[560px] place-items-center px-6 py-24 text-center">
                <div className="mb-3 grid h-14 w-14 place-items-center rounded-full bg-ink-sfw/15 text-ink-sfw">✓</div>
                <h1 className="text-[24px] font-extrabold tracking-[-0.02em]">Notice received</h1>
                <p className="mt-2 text-[14px] leading-relaxed text-ink-muted">
                    Your DMCA notice has been logged with a timestamp and will be reviewed by a
                    moderator. We keep a permanent record of every notice.
                </p>
                <Link href={route('gallery')} className="btn-primary mt-6">
                    Back to the gallery
                </Link>
            </div>
        </AppLayout>
    );
}
