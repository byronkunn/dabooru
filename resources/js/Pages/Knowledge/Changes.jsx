import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { Errors, Pagination, RevisionList } from '@/Components/KnowledgeUi';

function Report({ report }) {
    const form = useForm({ hide_discussion: false });
    return (
        <div className="my-3 rounded-xl border border-ink-line p-3">
            <p>Report #{report.id}: {report.target_type} #{report.target_id}</p>
            <p className="my-2 whitespace-pre-wrap">{report.reason}</p>
            {report.target_url && <Link className="text-ink-brand" href={report.target_url}>Inspect reported content</Link>}
            <form className="mt-3" onSubmit={(e) => { e.preventDefault(); form.post(route('admin.knowledge.report', report.id)); }}>
                {report.target_type === 'discussion' && <label className="flex gap-2"><input type="checkbox" checked={form.data.hide_discussion} onChange={(e) => form.setData('hide_discussion', e.target.checked)} />Hide reported discussion</label>}
                <Errors errors={form.errors} /><button disabled={form.processing} className="btn-ghost">Resolve report</button>
            </form>
        </div>
    );
}

export default function KnowledgeChanges({ revisions, moderation, reports }) {
    const Layout = moderation ? AdminLayout : AppLayout;
    const Header = moderation ? AdminTopBar : TopBar;
    return (
        <Layout>
            <Head title={moderation ? 'Community moderation' : 'Recent community changes'} />
            <Header title={moderation ? 'Community moderation' : 'Recent changes'} searchScope="knowledge" back={moderation ? null : route('knowledge.index')} />
            <div className="mx-auto max-w-3xl space-y-5 p-4 md:p-6">
                <p className="text-sm text-ink-muted">Content preferences apply to revision previews. Enable explicit content in settings to review explicit pages. Five independently moderator-approved edits unlock ordinary publishing.</p>
                <RevisionList revisions={revisions} />
                {moderation && <section>
                    <h2 className="text-xl font-bold">Reports</h2>
                    {reports.data.length === 0
                        ? <p className="py-8 text-center text-sm text-ink-muted">No reports in this view.</p>
                        : reports.data.map((report) => <Report key={report.id} report={report} />)}
                    <Pagination data={reports} />
                </section>}
            </div>
        </Layout>
    );
}
