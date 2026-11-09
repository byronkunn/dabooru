import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Errors } from '@/Components/KnowledgeUi';

export default function BugIndex({ kinds = {}, prefillUrl = '', recent = [] }) {
    const { url } = usePage();
    const [sending, setSending] = useState(false);
    const form = useForm({
        kind: 'bug',
        title: '',
        details: '',
        page_url: prefillUrl || (typeof window !== 'undefined' ? window.location.href : ''),
        viewport: typeof window !== 'undefined' ? `${window.innerWidth}x${window.innerHeight}` : '',
    });

    const submit = (e) => {
        e.preventDefault();
        setSending(true);
        form.post(route('bug.store'), {
            preserveScroll: true,
            onSuccess: () => { form.reset('title', 'details'); },
            onFinish: () => setSending(false),
        });
    };

    return (
        <AppLayout rightRail={false}>
            <Head title="Report a bug" />
            <TopBar title="Report a bug" back={route('gallery')} />

            <div className="mx-auto max-w-[640px] px-4 pb-24 pt-5 md:px-5">
                <p className="text-[13.5px] leading-relaxed text-ink-muted">
                    Tell us what went wrong and we will look at it. The page you are on and your screen size are
                    attached automatically, so you do not have to describe them.
                </p>

                <form onSubmit={submit} className="mt-5 space-y-3.5">
                    <label className="block text-[13px] font-semibold">
                        What kind of report is this?
                        <select
                            value={form.data.kind}
                            onChange={(e) => form.setData('kind', e.target.value)}
                            className="field-input mt-1 min-h-[42px] w-full text-[13.5px]"
                            aria-label="Report kind"
                        >
                            {Object.entries(kinds).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </label>

                    <label className="block text-[13px] font-semibold">
                        Summary
                        <input
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            maxLength={160}
                            required
                            placeholder="e.g. Uploading a GIF fails on mobile"
                            className="field-input mt-1 min-h-[42px] w-full text-[13.5px]"
                        />
                    </label>

                    <label className="block text-[13px] font-semibold">
                        What happened?
                        <textarea
                            value={form.data.details}
                            onChange={(e) => form.setData('details', e.target.value)}
                            rows={6}
                            maxLength={4000}
                            placeholder="Steps to reproduce, what you expected, what you saw instead."
                            className="field-input mt-1 w-full text-[13.5px]"
                        />
                    </label>

                    <div className="rounded-xl border border-ink-line bg-ink-surface px-3 py-2 text-[12px] text-ink-muted">
                        <p className="break-all">Page: {form.data.page_url || '-'}</p>
                        <p>Screen: {form.data.viewport || '-'}</p>
                    </div>

                    <Errors errors={form.errors} />
                    {form.recentlySuccessful && <p role="status" className="text-[13px] text-ink-sfw">Thanks — your report reached the moderators.</p>}

                    <button type="submit" disabled={sending || form.processing} className="btn-primary min-h-11 w-full">
                        {form.processing ? 'Sending…' : 'Send report'}
                    </button>
                </form>

                {recent.length > 0 && (
                    <section className="mt-8" aria-label="Your recent reports">
                        <h2 className="text-[15px] font-bold">Your recent reports</h2>
                        <ul className="mt-2 divide-y divide-ink-line">
                            {recent.map((report) => (
                                <li key={report.id} className="flex items-center gap-2 py-2 text-[13px]">
                                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold ${report.status === 'resolved' ? 'bg-ink-sfw/15 text-ink-sfw' : 'bg-ink-raised text-ink-muted'}`}>
                                        {report.status === 'resolved' ? 'Resolved' : 'Open'}
                                    </span>
                                    <span className="min-w-0 flex-1 truncate">{report.title}</span>
                                    <span className="shrink-0 text-[11.5px] text-ink-faint">{report.when}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <p className="mt-6 text-[12px] text-ink-faint">
                    For copyright takedowns use <a href={route('dmca')} className="text-ink-brand hover:underline">Copyright / DMCA</a> instead;
                    for content that breaks the rules use the flag on the work itself.
                </p>
            </div>
        </AppLayout>
    );
}
