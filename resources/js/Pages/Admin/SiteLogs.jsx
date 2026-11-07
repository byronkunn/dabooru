import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { AdminPagination } from '@/Components/AdminUi';

const HEALTH = [['queuedJobs', 'Database queue jobs'], ['failedJobs', 'Failed jobs'], ['olderVideos', 'Videos processing over 1 hour']];

export default function SiteLogs({ filters, events, health, failedJobs, previousPage, nextPage }) {
    const [query, setQuery] = useState({ category: '', severity: '', request_id: '', from: '', to: '', ...filters });
    const field = (key) => ({ value: query[key] ?? '', onChange: (event) => setQuery({ ...query, [key]: event.target.value }) });
    return (
        <AdminLayout>
            <Head title="Site logs" />
            <AdminTopBar title="Site logs" subtitle="Errors and queue health" />
            <div className="space-y-5 p-3.5 md:p-5">
                <dl className="grid gap-3 sm:grid-cols-3">
                    {HEALTH.map(([key, label]) => <div key={key} className="panel"><dt className="text-sm text-ink-muted">{label}</dt><dd className="mt-1 text-2xl font-bold">{health[key].toLocaleString()}</dd></div>)}
                </dl>
                {failedJobs.length > 0 && <section className="panel">
                    <h2 className="mb-2 font-bold">Recent failed jobs</h2>
                    <ul className="space-y-2">{failedJobs.map((job) => <li key={job.uuid} className="break-all text-xs text-ink-muted"><code>{job.uuid}</code> · {job.failed_at}</li>)}</ul>
                </section>}
                <section className="space-y-3">
                    <h2 className="font-bold">Application events · last 30 days</h2>
                    <form className="panel grid gap-3 sm:grid-cols-2 lg:grid-cols-3" onSubmit={(event) => {
                        event.preventDefault();
                        router.get(route('admin.site-logs'), query, { preserveState: true });
                    }}>
                        <label className="text-sm">Category
                            <select {...field('category')} className="field-input mt-1">
                                <option value="">All categories</option><option value="application">Application</option><option value="mail">Mail transport</option>
                            </select>
                        </label>
                        <label className="text-sm">Severity
                            <select {...field('severity')} className="field-input mt-1"><option value="">All severities</option><option value="error">Error</option></select>
                        </label>
                        <label className="min-w-0 text-sm">Request ID<input {...field('request_id')} className="field-input mt-1" /></label>
                        {['from', 'to'].map((key) => <label className="min-w-0 text-sm capitalize" key={key}>{key}<input type="date" {...field(key)} className="field-input mt-1 min-w-0" /></label>)}
                        <button className="btn-ghost self-end">Apply filters</button>
                    </form>
                    {events.length ? <ul className="space-y-2">
                        {events.map((event) => <li key={event.id} className="panel space-y-1 break-all text-sm">
                            <p><strong className="capitalize text-ink-expl">{event.severity}</strong> · {event.exception} · {event.category}</p>
                            <p className="text-xs text-ink-muted">{event.created_at}{event.route_name ? ` · ${event.route_name}` : ''}</p>
                            {event.request_id && <code className="text-xs text-ink-faint">{event.request_id}</code>}
                        </li>)}
                    </ul> : <p className="py-8 text-center text-sm text-ink-muted">No events match these filters.</p>}
                    <AdminPagination {...{ previousPage, nextPage }} />
                </section>
            </div>
        </AdminLayout>
    );
}
