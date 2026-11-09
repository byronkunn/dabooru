import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Errors, ReportKnowledge } from '@/Components/KnowledgeUi';

export default function KnowledgeRevision({ revision }) {
    const { auth } = usePage().props;
    const form = useForm({ action: revision.status === 'accepted' ? 'revert' : 'accept', reason: '' });
    const restriction = useForm({ restricted: true });
    const keys = [...new Set([...Object.keys(revision.before), ...Object.keys(revision.after)])].filter((key) => JSON.stringify(revision.before[key]) !== JSON.stringify(revision.after[key]));
    return (
        <AppLayout>
            <Head title={`Revision #${revision.id}`} /><TopBar title={`Revision #${revision.id}`} searchScope="knowledge" back={revision.target_url} />
            <div className="mx-auto max-w-3xl space-y-5 p-4 md:p-6">
                <h1 className="text-xl font-bold">{revision.summary}</h1>
                <p className="text-ink-muted">{revision.status} · {revision.editor ? `@${revision.editor}` : 'Deleted contributor'}{revision.reviewer ? ` · reviewed by @${revision.reviewer}` : ''}</p>
                <Link href={revision.target_url} className="text-ink-brand">Open current page</Link>
                {keys.map((key) => (
                    <section key={key}>
                        <h2 className="mb-2 font-bold">{key}</h2>
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="rounded-xl border border-red-400/30 p-3"><h3 className="text-sm text-ink-muted">Before</h3><pre className="overflow-x-auto whitespace-pre-wrap break-words text-sm">{typeof revision.before[key] === 'string' ? revision.before[key] : JSON.stringify(revision.before[key], null, 2)}</pre></div>
                            <div className="rounded-xl border border-green-400/30 p-3"><h3 className="text-sm text-ink-muted">After</h3><pre className="overflow-x-auto whitespace-pre-wrap break-words text-sm">{typeof revision.after[key] === 'string' ? revision.after[key] : JSON.stringify(revision.after[key], null, 2)}</pre></div>
                        </div>
                    </section>
                ))}
                {revision.review_reason && <p>Moderator reason: {revision.review_reason}</p>}
                {auth.user?.staff_permissions?.includes('curator') && ['pending', 'accepted'].includes(revision.status) && (
                    <form className="panel space-y-3" onSubmit={(e) => { e.preventDefault(); form.transform((data) => ({ ...data, action: revision.status === 'accepted' ? 'revert' : data.action }));
form.post(route('admin.knowledge.review', revision.id)); }}>
                        <label className="block">Action<select className="field-input" value={revision.status === 'accepted' ? 'revert' : form.data.action} onChange={(e) => form.setData('action', e.target.value)}>
                            {revision.status === 'pending' ? <><option value="accept">Approve and publish</option><option value="reject">Reject</option></> : <option value="revert">Revert with an audit revision</option>}
                        </select></label>
                        <label className="block">Reason<input className="field-input" required minLength={3} maxLength={500} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} /></label>
                        <Errors errors={form.errors} />
                        <button disabled={form.processing} className="btn-primary">Apply review</button>
                    </form>
                )}
                {auth.user?.staff_permissions?.includes('curator') && revision.editor && <details><summary className="cursor-pointer text-sm text-ink-muted">Contributor permissions</summary>
                    <form className="mt-3 space-y-3" onSubmit={(e) => { e.preventDefault(); restriction.post(route('admin.knowledge.restrict', revision.editor)); }}>
                        <label className="flex gap-2"><input type="checkbox" checked={restriction.data.restricted} onChange={(e) => restriction.setData('restricted', e.target.checked)} />Restrict community contributions</label>
                        <Errors errors={restriction.errors} /><button disabled={restriction.processing} className="btn-ghost">Update permissions</button>
                    </form>
                </details>}
                {auth.user?.email_verified && <ReportKnowledge type="revision" id={revision.id} />}
            </div>
        </AppLayout>
    );
}
