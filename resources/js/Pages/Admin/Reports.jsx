import { Head, Link, router } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';
import { useState } from 'react';
import { AdminTabs } from '@/Components/AdminTabs';

const TABS = [
    ['open', 'Open'],
    ['actioned', 'Actioned'],
    ['dismissed', 'Dismissed'],
];

export default function AdminReports({ reports, status, counts, reasons }) {
    const [reason, setReason] = useState('');

    const act = (report, action) => {
        const url = action === 'remove' ? route('admin.reports.remove', report.id) : route('admin.reports.dismiss', report.id);
        router.post(url, { reason }, { preserveScroll: true, onSuccess: () => setReason('') });
    };

    return (
        <AppLayout rightRail={false}>
            <Head title="Moderation queue" />
            <TopBar
                title="Moderation"
                subtitle={`${counts.open} open`}
                tabs={
                    <div className="flex gap-1.5 overflow-x-auto px-3.5 pb-3 md:px-5">
                        {TABS.map(([key, label]) => (
                            <Link key={key} href={route('admin.reports', { status: key })} className={status === key ? 'chip is-active' : 'chip'}>
                                {label} <span className="opacity-70">{counts[key]}</span>
                            </Link>
                        ))}
                    </div>
                }
            />
            <AdminTabs />

            <div className="px-3.5 py-3 md:px-5">
                <input
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    placeholder="Optional note for the log (shown in the record of this action)"
                    className="field-input mb-3"
                    maxLength={500}
                    aria-label="Moderator note"
                />
            </div>

            {reports.length === 0 ? (
                <div className="grid place-items-center gap-2 px-6 py-24 text-center">
                    <div className="grid h-14 w-14 place-items-center rounded-full bg-ink-raised">
                        <Icon name="check" className="h-6 w-6 text-ink-sfw" />
                    </div>
                    <p className="text-[15px] text-ink-muted">Nothing {status}.</p>
                </div>
            ) : (
                <ul>
                    {reports.map((report) => (
                        <li key={report.id} className="border-b border-ink-line px-3.5 py-4 md:px-5">
                            <div className="flex items-start gap-3">
                                {report.image ? (
                                    <img src={report.image} alt="" className={`h-16 w-16 shrink-0 rounded-lg bg-ink-raised object-cover ${report.reason === 'minor' ? 'blur-md' : ''}`} />
                                ) : (
                                    <span className="grid h-16 w-16 shrink-0 place-items-center rounded-lg bg-ink-raised text-ink-faint">
                                        <Icon name="flag" className="h-5 w-5" />
                                    </span>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-1.5 text-[13.5px]">
                                        <span className={`rounded-md px-1.5 py-0.5 text-[11px] font-extrabold uppercase tracking-wide ${report.reason === 'minor' ? 'bg-ink-expl/20 text-[#ff98a9]' : 'bg-ink-raised text-ink-muted'}`}>
                                            {report.reason_label}
                                        </span>
                                        <span className="text-ink-muted">
                                            {report.reportable_type} #{report.reportable_id}
                                        </span>
                                        <span className="text-ink-faint">·</span>
                                        <span className="text-ink-faint">{report.created_at_human}</span>
                                        {report.reporter && (
                                            <>
                                                <span className="text-ink-faint">·</span>
                                                <span className="text-ink-faint">reported by @{report.reporter}</span>
                                            </>
                                        )}
                                    </p>
                                    {report.preview && (
                                        <p className="mt-1 line-clamp-2 text-[14px]">
                                            {report.url ? (
                                                <a href={report.url} target="_blank" rel="noreferrer" className="hover:underline">
                                                    {report.preview}
                                                </a>
                                            ) : (
                                                report.preview
                                            )}
                                        </p>
                                    )}
                                    {report.details && (
                                        <p className="mt-1 rounded-lg bg-ink-raised px-2 py-1.5 text-[12.5px] text-ink-muted">
                                            “{report.details}”
                                        </p>
                                    )}
                                    {report.author && (
                                        <p className="mt-1 text-[12.5px] text-ink-faint">
                                            by {report.author.name} (@{report.author.username})
                                        </p>
                                    )}
                                </div>
                            </div>

                            {status === 'open' ? (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <button type="button" onClick={() => act(report, 'remove')} className="btn min-h-[38px] rounded-full bg-ink-expl px-4 text-[13px] text-white hover:brightness-110">
                                        Remove &amp; close
                                    </button>
                                    <button type="button" onClick={() => act(report, 'dismiss')} className="btn-ghost min-h-[38px] text-[13px]">
                                        Dismiss
                                    </button>
                                    {report.url && (
                                        <a href={report.url} target="_blank" rel="noreferrer" className="btn-ghost min-h-[38px] text-[13px]">
                                            Open
                                        </a>
                                    )}
                                </div>
                            ) : (
                                <p className="mt-2 text-[12.5px] text-ink-faint">
                                    {report.status} by {report.handler ? `@${report.handler}` : 'a moderator'} {report.resolved_at_human}
                                    {report.details ? ` — ${report.details}` : ''}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <p className="px-4 py-6 text-center text-[12px] text-ink-faint">
                Removing a post soft-deletes it and closes the report. Every action is written to
                the moderation log and cannot be deleted.
            </p>
        </AppLayout>
    );
}
