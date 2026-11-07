import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Errors, Pagination } from '@/Components/KnowledgeUi';
import { useState } from 'react';

export default function KnowledgeIndex({ tags, articles, categories, filters, canContribute }) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [category, setCategory] = useState(filters.category ?? '');
    const [needsWiki, setNeedsWiki] = useState(Boolean(Number(filters.needs_wiki ?? 0)));
    const form = useForm({ type: 'tag', name: '' });
    return (
        <AppLayout>
            <Head title="Community tags & wiki" />
            <TopBar title="Tags & wiki" />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <p className="text-ink-muted">Community-maintained descriptions of artwork. Adult-content categories describe works, not members. Ratings, ownership, and verified artist credit are managed separately.</p>
                <div className="flex flex-wrap gap-2">
                    <Link href={route('knowledge.changes')} className="btn-ghost">Recent changes</Link>
                    <Link href={route('knowledge.index', { needs_wiki: 1 })} className="btn-ghost">Needs a wiki</Link>
                </div>
                <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); router.get(route('knowledge.index'), { q: query, category: category || undefined, needs_wiki: needsWiki ? 1 : 0 }); }}>
                    <label className="block">Find tags<input className="field-input" value={query} maxLength={160} onChange={(e) => setQuery(e.target.value)} placeholder="Canonical names or aliases" /></label>
                    <label className="block">Category<select className="field-input" value={category} onChange={(e) => setCategory(e.target.value)}>
                        <option value="">All categories</option>{categories.map((value) => <option key={value}>{value}</option>)}
                    </select></label>
                    <label className="flex gap-2"><input type="checkbox" checked={needsWiki} onChange={(e) => setNeedsWiki(e.target.checked)} />Only tags needing a wiki</label>
                    <button className="btn-primary">Find tags</button>
                </form>
                <section>
                    <h2 className="text-xl font-bold">Tag directory</h2>
                    {tags.data.length === 0 && <p className="mt-3 text-ink-muted">No visible tags match these filters.</p>}
                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                        {tags.data.map((tag) => (
                            <Link key={tag.id} href={route('knowledge.show', { type: 'tag', id: tag.id })} className="rounded-xl border border-ink-line p-3 hover:bg-ink-raised">
                                <b className="text-ink-brand">{tag.name}</b>
                                <p className="text-sm text-ink-muted">{tag.category} · {tag.posts_count} visible works{tag.locked ? ' · locked' : ''}</p>
                            </Link>
                        ))}
                    </div>
                    <Pagination data={tags} />
                </section>
                <section>
                    <h2 className="text-xl font-bold">Wiki guides</h2>
                    {articles.data.map((article) => <Link key={article.id} href={route('knowledge.show', { type: 'wiki', id: article.id })} className="my-2 block text-ink-brand hover:underline">{article.title}{!article.body ? ' (draft)' : ''}</Link>)}
                    <Pagination data={articles} />
                </section>
                {canContribute ? (
                    <form className="panel space-y-3" onSubmit={(e) => { e.preventDefault(); form.post(route('knowledge.create')); }}>
                        <h2 className="text-lg font-bold">Start a page</h2>
                        <p className="text-sm text-ink-muted">New pages start as empty drafts. Definitions and changes go through review.</p>
                        <label className="block">Page type<select className="field-input" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}><option value="tag">Tag wiki</option><option value="wiki">Standalone guide</option></select></label>
                        <label className="block">Name or title<input className="field-input" required maxLength={form.data.type === 'tag' ? 40 : 160} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
                        <Errors errors={form.errors} /><button disabled={form.processing} className="btn-primary">Start draft</button>
                    </form>
                ) : <p className="text-sm text-ink-muted">Log in with a verified, unrestricted account to contribute.</p>}
            </div>
        </AppLayout>
    );
}
