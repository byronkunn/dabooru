import { Head, Link } from '@inertiajs/react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';

function Metric({ metric, dates }) {
    const max = Math.max(1, ...metric.daily);
    return (
        <article className="panel min-w-0">
            <h2 className="text-sm text-ink-muted">{metric.label}</h2>
            <p className="text-2xl font-bold">{metric.total.toLocaleString()}</p>
            <div className="mt-3 flex h-14 items-end gap-0.5" aria-hidden="true">
                {metric.daily.map((value, index) => <span key={dates[index]} title={`${dates[index]}: ${value}`} className="min-w-0 flex-1 rounded-t-sm bg-ink-brand/70" style={{ height: `${value ? value / max * 100 : 0}%` }} />)}
            </div>
            <details className="mt-2 text-sm">
                <summary className="cursor-pointer text-xs text-ink-muted">Daily breakdown</summary>
                <dl className="mt-2 space-y-1">{metric.daily.map((value, index) => <div key={dates[index]} className="flex justify-between"><dt>{dates[index]}</dt><dd>{value.toLocaleString()}</dd></div>)}</dl>
            </details>
        </article>
    );
}

export default function Analytics({ days, dates, metrics, meanResolutionMinutes }) {
    const resolution = meanResolutionMinutes === null ? '—' : meanResolutionMinutes >= 60
        ? `${(meanResolutionMinutes / 60).toLocaleString(undefined, { maximumFractionDigits: 1 })} h`
        : `${meanResolutionMinutes} min`;

    return (
        <AdminLayout>
            <Head title="Analytics" />
            <AdminTopBar title="Analytics" subtitle="Site activity" />
            <div className="space-y-4 p-3.5 md:p-5">
                <div className="flex flex-wrap items-center gap-2">
                    {[7, 30].map((value) => <Link key={value} href={route('admin.analytics', { days: value })} className={days === value ? 'chip is-active' : 'chip'}>Last {value} days</Link>)}
                    <span className="text-xs text-ink-muted">{dates[0]} to {dates.at(-1)}</span>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {Object.entries(metrics).map(([key, metric]) => <Metric key={key} {...{ metric, dates }} />)}
                    <article className="panel">
                        <h2 className="text-sm text-ink-muted">Average report resolution</h2>
                        <p className="text-2xl font-bold">{resolution}</p>
                        <p className="mt-2 text-xs text-ink-faint">From submission to closure for reports closed in this period.</p>
                    </article>
                </div>
                <p className="text-xs text-ink-muted">Work views count one view per work, viewer, and day. Saved works count bookmarks still saved.</p>
            </div>
        </AdminLayout>
    );
}
