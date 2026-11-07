import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { AdminErrors, AdminPagination } from '@/Components/AdminUi';
import { Icon } from '@/Components/Icons';

const TABS = [['open', 'Open'], ['actioned', 'Actioned'], ['dismissed', 'Dismissed']];
const RATINGS = [['sfw', 'Safe for work'], ['suggestive', 'Suggestive'], ['explicit', 'Explicit']];

function ReportCard({ report, viewer }) {
    const [decision, setDecision] = useState('');
    const form = useForm({ reason: '', rating: report.rating ?? 'sfw', claim: false });
    const open = report.status === 'open';
    const canClaim = !report.assigned_to || report.assigned_to === viewer.id || viewer.is_admin;
    const actions = [
        ...(report.available ? [['remove', report.reportable_type === 'User' ? 'Ban account' : 'Remove content']] : []),
        ...(report.rating && !report.sensitive ? [['rating', 'Correct rating']] : []),
        ['dismiss', 'Dismiss report'],
    ];
    const submit = (event) => {
        event.preventDefault();
        if (!decision) return;
        const url = decision === 'restore'
            ? route('admin.works.restore', report.reportable_id)
            : route(`admin.reports.${decision}`, report.id);
        form.transform(({ reason, rating }) => ({ reason, ...(decision === 'rating' ? { rating } : {}) }));
        form.post(url, { preserveScroll: true, onSuccess: () => { form.reset('reason'); setDecision(''); } });
    };

    return (
        <li className="panel space-y-3">
            <div className="flex items-start gap-3">
                {report.image ? <img src={report.image} alt="" loading="lazy" className="h-14 w-14 shrink-0 rounded-lg object-cover" />
                    : <span className="grid h-14 w-14 shrink-0 place-items-center rounded-lg bg-ink-raised text-ink-faint"><Icon name="flag" /></span>}
                <div className="min-w-0 space-y-1">
                    <p className="flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                        {report.urgent && <strong className="rounded bg-ink-expl/15 px-2 py-0.5 text-ink-expl">Urgent</strong>}
                        <strong className="text-ink-text">{report.reason_label}</strong>
                        <span>Report #{report.id} · {report.created_at_human}</span>
                    </p>
                    <p className="break-words text-sm font-semibold">{report.reportable_type} #{report.reportable_id}{report.preview && ` · ${report.preview}`}</p>
                    {report.details && <p className="break-words text-sm text-ink-muted">{report.details}</p>}
                    <p className="break-words text-xs text-ink-faint">
                        {report.reporter ? `Reported by @${report.reporter} · ` : ''}
                        {open ? (report.assignee ? `Claimed by @${report.assignee}` : 'Unassigned') : `${report.status} by ${report.handler ? `@${report.handler}` : 'staff'}`}
                    </p>
                </div>
            </div>
            <div className="flex flex-wrap gap-2">
                {report.url && !report.sensitive && <Link href={report.url} className="btn-ghost">Open item</Link>}
                {report.related_open > 1 && <Link href={route('admin.reports', { target_type: report.reportable_type, target_id: report.reportable_id })} className="btn-ghost">{report.related_open} open reports on this item</Link>}
                {open && canClaim && (
                    <button type="button" className="btn-ghost" disabled={form.processing} onClick={() => {
                        form.transform(() => ({ claim: report.assigned_to !== viewer.id }));
                        form.post(route('admin.reports.assign', report.id), { preserveScroll: true });
                    }}>{report.assigned_to === viewer.id ? 'Release' : 'Claim case'}</button>
                )}
                {open && actions.map(([value, label]) => (
                    <button key={value} type="button" className={decision === value ? 'btn-primary' : 'btn-ghost'} aria-pressed={decision === value} onClick={() => { form.clearErrors(); setDecision(value); }}>{label}</button>
                ))}
                {!open && report.can_restore && <button type="button" className="btn-ghost" onClick={() => setDecision('restore')}>Restore work</button>}
            </div>
            {report.url && report.sensitive && (
                <details className="text-sm">
                    <summary className="cursor-pointer text-ink-muted">Sensitive item · review link</summary>
                    <Link href={report.url} className="link-brand mt-2 inline-block">Open reported item</Link>
                </details>
            )}
            {decision && (
                <form className="space-y-2 rounded-xl border border-ink-line bg-ink-raised p-3" onSubmit={submit}>
                    {decision === 'rating' && <label className="block text-sm">New rating
                        <select className="field-input mt-1" value={form.data.rating} onChange={(event) => form.setData('rating', event.target.value)}>
                            {RATINGS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </label>}
                    <label className="block text-sm">Decision note
                        <textarea className="field-input mt-1" rows={2} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} maxLength={500} required={decision !== 'dismiss'} />
                    </label>
                    <div className="flex flex-wrap gap-2">
                        <button className="btn-primary" disabled={form.processing}>Confirm {decision === 'rating' ? 'rating change' : decision === 'remove' ? 'action' : decision === 'dismiss' ? 'dismissal' : 'restore'}</button>
                        <button type="button" className="btn-ghost" onClick={() => { form.clearErrors(); setDecision(''); }}>Cancel</button>
                    </div>
                </form>
            )}
            <AdminErrors errors={form.errors} />
        </li>
    );
}

export default function AdminReports({ reports, status, counts, filters, urgentCount, previousPage, nextPage }) {
    const { auth } = usePage().props;
    const query = { ...filters, status };
    return (
        <AdminLayout>
            <Head title="Reports" />
            <AdminTopBar title="Reports" subtitle={`${counts.open} open · ${urgentCount} urgent`} />
            <div className="space-y-4 p-3.5 md:p-5">
                <nav className="flex flex-wrap gap-2" aria-label="Report status">
                    {TABS.map(([key, label]) => <Link key={key} href={route('admin.reports', { status: key })} className={status === key && !filters.report ? 'chip is-active' : 'chip'}>{label} {counts[key]}</Link>)}
                </nav>
                {!filters.report && status === 'open' && <nav className="flex flex-wrap gap-2" aria-label="Report filters">
                    <Link className={filters.priority ? 'chip is-active' : 'chip'} href={route('admin.reports', { ...query, priority: filters.priority ? null : 'urgent' })}>Urgent</Link>
                    {[['mine', 'My cases'], ['unassigned', 'Unassigned']].map(([key, label]) => <Link key={key} className={filters.assignment === key ? 'chip is-active' : 'chip'} href={route('admin.reports', { ...query, assignment: filters.assignment === key ? null : key })}>{label}</Link>)}
                </nav>}
                {(filters.report || filters.target_id) && <Link href={route('admin.reports')} className="link-brand text-sm">Back to all reports</Link>}
                {reports.length > 0 ? <ul className="space-y-3">{reports.map((report) => <ReportCard key={`${report.id}-${report.status}`} report={report} viewer={auth.user} />)}</ul>
                    : <p className="py-14 text-center text-sm text-ink-muted">No reports match this view.</p>}
                <AdminPagination {...{ previousPage, nextPage }} />
            </div>
        </AdminLayout>
    );
}
