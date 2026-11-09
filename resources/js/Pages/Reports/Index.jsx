import { Head, Link } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';

const STATE_STYLE = {
    received: { chip: 'border-ink-line bg-ink-raised text-ink-muted', icon: 'bell' },
    under_review: { chip: 'border-ink-brand/45 bg-ink-brand/15 text-ink-brand', icon: 'flag' },
    closed: { chip: 'border-ink-line bg-ink-surface text-ink-faint', icon: 'check' },
};

const STATE_HELP = {
    received: 'We have it. A moderator has not picked it up yet.',
    under_review: 'A moderator has claimed this report and is looking at it.',
    closed: 'Reviewed. You are not told who handled it or what was decided about the other account.',
};

export default function ReportsIndex({ reports, summary }) {
    const items = reports?.data ?? [];

    return (
        <AppLayout rightRail={false}>
            <Head title="Your reports" />
            <TopBar title="Your reports" back={route('settings.edit')} />

            <div className="mx-auto max-w-[720px]">
                <p className="px-4 pt-4 text-[13px] leading-relaxed text-ink-muted md:px-5">
                    Reports you have filed, and where each one stands. Reporting stays private — the account you
                    reported is never told who reported them.
                </p>

                <div className="grid grid-cols-3 gap-2 px-4 pt-3 md:px-5">
                    {[
                        ['received', 'Received'],
                        ['under_review', 'Under review'],
                        ['closed', 'Closed'],
                    ].map(([key, label]) => (
                        <div key={key} className="rounded-xl border border-ink-line bg-ink-surface px-3 py-2.5 text-center">
                            <p className="text-[18px] font-extrabold">{summary?.[key] ?? 0}</p>
                            <p className="text-[11.5px] text-ink-muted">{label}</p>
                        </div>
                    ))}
                </div>

                {items.length === 0 ? (
                    <div className="grid place-items-center gap-3 px-5 py-20 text-center">
                        <Icon name="flag" className="h-8 w-8 text-ink-faint" />
                        <p className="text-[15px] text-ink-muted">You haven&apos;t reported anything.</p>
                        <p className="max-w-[320px] text-[13px] text-ink-faint">
                            Use the flag on a work, comment, message or account to report it. What you send is
                            only visible to moderators.
                        </p>
                    </div>
                ) : (
                    <ul className="mt-3 pb-16">
                        {items.map((report) => {
                            const style = STATE_STYLE[report.state] ?? STATE_STYLE.received;
                            return (
                                <li key={report.id} className="border-b border-ink-line px-4 py-3.5 md:px-5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11.5px] font-bold ${style.chip}`}>
                                            <Icon name={style.icon} className="h-3 w-3" />
                                            {report.state_label}
                                        </span>
                                        <span className="text-[13.5px] font-semibold">{report.reason_label}</span>
                                        <span className="text-[12px] text-ink-faint">
                                            {report.target_label} · {report.created_at_human}
                                        </span>
                                    </div>

                                    <p className="mt-1 text-[12.5px] text-ink-muted">
                                        {report.state === 'closed' && report.outcome
                                            ? `${report.outcome} · closed ${report.closed_at_human}`
                                            : STATE_HELP[report.state]}
                                    </p>

                                    {/* the reported item is only linked when it is still publicly visible */}
                                    {report.state === 'closed' && report.target_type === 'Post' && (
                                        <Link
                                            href={route('posts.show', report.target_id)}
                                            className="mt-1 inline-block text-[12.5px] font-semibold text-ink-brand hover:underline"
                                        >
                                            View work
                                        </Link>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
