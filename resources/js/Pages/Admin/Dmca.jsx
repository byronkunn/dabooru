import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { AdminErrors, AdminPagination } from '@/Components/AdminUi';

const STATUSES = [['open', 'Open'], ['actioned', 'Taken down'], ['resolved', 'Resolved'], ['rejected', 'Rejected']];

function NoticeCard({ notice, viewer }) {
    const [decision, setDecision] = useState('');
    const form = useForm({ reason: '' });
    const act = (action) => {
        const href = action === 'restore' ? route('admin.works.restore', notice.post.id) : route('admin.dmca.update', notice.id);
        form.transform(({ reason }) => ({ reason, ...(action === 'restore' ? {} : { action }) }));
        form.post(href, { preserveScroll: true, onSuccess: () => { form.reset(); setDecision(''); } });
    };
    const open = notice.status === 'open';
    const actions = [
        ...(open && notice.post ? [['takedown', 'Take down work']] : []),
        ...(open ? [['resolve', 'Resolve notice'], ['reject', 'Reject notice']] : []),
        ...(notice.status === 'actioned' ? [['resolve', 'Resolve and release hold']] : []),
        ...(notice.status === 'resolved' && notice.post?.restorable ? [['restore', 'Restore work']] : []),
    ];

    return (
        <li className="panel space-y-3 break-words">
            <div>
                <p className="font-bold">Notice #{notice.id} · {notice.reporter_name}</p>
                <p className="text-sm text-ink-muted">{notice.reporter_email}{notice.rights_holder ? ` · Rights holder: ${notice.rights_holder}` : ''}</p>
                <p className="text-xs text-ink-faint"><time dateTime={notice.received}>{new Date(notice.received).toLocaleString()}</time> · {notice.handler ? `@${notice.handler}` : 'Unassigned'}</p>
            </div>
            <p className="break-all text-xs text-ink-muted">{notice.url}</p>
            {notice.post ? (
                <p className="text-sm">
                    {notice.post.visible ? <Link href={route('posts.show', notice.post.id)} className="link-brand">{notice.post.title ?? `Work #${notice.post.id}`}</Link>
                        : <span>Work #{notice.post.id} · hidden</span>}
                </p>
            ) : <p className="text-sm text-ink-muted">No work matched this URL.</p>}
            <details>
                <summary className="cursor-pointer text-sm font-semibold">Notice details</summary>
                <p className="mt-2 whitespace-pre-wrap text-sm text-ink-muted">{notice.description}</p>
            </details>
            {notice.resolution && <p className="whitespace-pre-wrap text-sm">Decision: {notice.resolution}</p>}
            <div className="flex flex-wrap gap-2">
                {open && <button className="btn-ghost" disabled={form.processing} onClick={() => act(notice.claimed_by === viewer.id ? 'release' : 'claim')}>{notice.claimed_by === viewer.id ? 'Release notice' : 'Claim notice'}</button>}
                {actions.map(([value, label]) => <button key={value} type="button" className={decision === value ? 'btn-primary' : 'btn-ghost'} aria-pressed={decision === value} onClick={() => { form.clearErrors(); setDecision(value); }}>{label}</button>)}
            </div>
            {decision && <form className="space-y-2" onSubmit={(event) => { event.preventDefault(); act(decision); }}>
                <label className="block text-sm">Decision note
                    <textarea className="field-input mt-1" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} rows={2} maxLength={500} required />
                </label>
                <button className="btn-primary" disabled={form.processing}>Confirm decision</button>
                <button type="button" className="btn-ghost ml-2" onClick={() => { form.clearErrors(); setDecision(''); }}>Cancel</button>
            </form>}
            <AdminErrors errors={form.errors} />
        </li>
    );
}

export default function AdminDmca({ notices, status, counts, previousPage, nextPage }) {
    const { auth } = usePage().props;
    return (
        <AdminLayout>
            <Head title="DMCA notices" />
            <AdminTopBar title="DMCA notices" subtitle={`${counts.open ?? 0} open`} />
            <div className="space-y-4 p-3.5 md:p-5">
                <nav className="flex flex-wrap gap-2" aria-label="Notice status">
                    {STATUSES.map(([key, label]) => <Link key={key} href={route('admin.dmca', { status: key })} className={status === key ? 'chip is-active' : 'chip'}>{label} {counts[key] ?? 0}</Link>)}
                </nav>
                {notices.length ? <ul className="space-y-3">{notices.map((notice) => <NoticeCard key={`${notice.id}-${notice.status}`} notice={notice} viewer={auth.user} />)}</ul>
                    : <p className="py-10 text-center text-sm text-ink-muted">No notices in this view.</p>}
                <AdminPagination {...{ previousPage, nextPage }} />
            </div>
        </AdminLayout>
    );
}
