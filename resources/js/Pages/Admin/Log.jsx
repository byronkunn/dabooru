import { Head, Link } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { AdminTabs } from '@/Components/AdminTabs';

const ACTION_LABEL = {
    remove_post: 'Removed post',
    remove_comment: 'Removed comment',
    remove_message: 'Removed message',
    remove_content: 'Removed content',
    ban_user: 'Banned user',
    unban_user: 'Unbanned user',
    dismiss_report: 'Dismissed report',
    dmca_notice: 'DMCA notice received',
};

export default function AdminLog({ actions, dmca, totalActions, openDmca }) {
    return (
        <AppLayout rightRail={false}>
            <Head title="Moderation log" />
            <TopBar title="Moderation" subtitle={`${totalActions} actions recorded`} />
            <AdminTabs />

            <section className="px-3.5 py-4 md:px-5" aria-labelledby="dmca-heading">
                <h2 id="dmca-heading" className="mb-2 text-[15px] font-bold">
                    DMCA notices <span className="text-[13px] font-medium text-ink-muted">({openDmca} open)</span>
                </h2>
                {dmca.length === 0 ? (
                    <p className="text-[13px] text-ink-muted">No notices received.</p>
                ) : (
                    <ul className="space-y-2">
                        {dmca.map((notice) => (
                            <li key={notice.id} className="panel">
                                <p className="text-[13px] font-bold">
                                    {notice.reporter_name} <span className="font-medium text-ink-muted">· {notice.status}</span>
                                </p>
                                <p className="mt-0.5 break-all text-[12.5px] text-ink-muted">{notice.url}</p>
                                <p className="mt-1 text-[13px]">{notice.description}</p>
                                <p className="mt-1 text-[11.5px] text-ink-faint">{notice.when_human} · handled by {notice.handler ?? 'nobody yet'}</p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="border-t border-ink-line px-3.5 py-4 md:px-5" aria-labelledby="log-heading">
                <h2 id="log-heading" className="mb-2 text-[15px] font-bold">
                    Action log
                </h2>
                {actions.length === 0 ? (
                    <p className="text-[13px] text-ink-muted">Nothing yet. Every removal, ban and dismissal lands here and cannot be deleted.</p>
                ) : (
                    <ul className="divide-y divide-ink-line">
                        {actions.map((action) => (
                            <li key={action.id} className="flex flex-wrap items-baseline gap-x-2 py-2 text-[13px]">
                                <span className="font-bold">{ACTION_LABEL[action.action] ?? action.action}</span>
                                <span className="text-ink-muted">
                                    {action.target_type} {action.target_id ? `#${action.target_id}` : ''}
                                </span>
                                <span className="text-ink-faint">by @{action.moderator} · {action.when_human}</span>
                                {action.reason && <span className="w-full text-[12.5px] text-ink-muted">“{action.reason}”</span>}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AppLayout>
    );
}
