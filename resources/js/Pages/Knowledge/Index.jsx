import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Errors, Pagination } from '@/Components/KnowledgeUi';
import { useEffect, useState } from 'react';
import TagFollowButton from '@/Components/TagFollowButton';
import { categoryColor, categoryLabel } from '@/Lib/tagCategories';
import { usePage } from '@inertiajs/react';
import SearchBox from '@/Components/SearchBox';

export default function KnowledgeIndex({ tags, articles, categories, filters, canContribute }) {
    const { auth } = usePage().props;
    const [query, setQuery] = useState(filters.q ?? '');
    useEffect(() => setQuery(filters.q ?? ''), [filters.q]);
    const [category, setCategory] = useState(filters.category ?? '');
    const [needsWiki, setNeedsWiki] = useState(Boolean(Number(filters.needs_wiki ?? 0)));
    const form = useForm({ type: 'tag', name: '' });
    return (
        <AppLayout>
            <Head title="Community tags & wiki" />
            <TopBar title="Tags & wiki" search={false} />
            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <p className="text-ink-muted">Community-maintained descriptions of artwork. Adult-content categories describe works, not members. Ratings, ownership, and verified artist credit are managed separately.</p>
                <div className="flex flex-wrap gap-2">
                    <Link href={route('knowledge.changes')} className="btn-ghost">Recent changes</Link>
                    <Link href={route('knowledge.index', { needs_wiki: 1 })} className="btn-ghost">Needs a wiki</Link>
                    {auth.user?.is_admin && <Link href={route('admin.tags.index')} className="btn-primary">Manage tags</Link>}
                </div>
                <div className="space-y-3">
                    <SearchBox scope="knowledge" value={query} onChange={setQuery} onSearch={(q) => {
                        router.get(route('knowledge.index'), { q, category: category || undefined, needs_wiki: needsWiki ? 1 : 0 });
                    }} />
                    <label className="block">Category<select className="field-input" value={category} onChange={(e) => setCategory(e.target.value)}>
                        <option value="">All categories</option>{categories.map((value) => <option key={value} value={value}>{categoryLabel(value)}</option>)}
                    </select></label>
                    <label className="flex gap-2"><input type="checkbox" checked={needsWiki} onChange={(e) => setNeedsWiki(e.target.checked)} />Only tags needing a wiki</label>
                </div>
                <nav aria-label="Tag categories" className="flex flex-wrap gap-2">
                    <Link href={route('knowledge.index', { ...filters, category: undefined, page: undefined })}
                        aria-current={!filters.category ? 'page' : undefined} className={!filters.category ? 'chip is-active' : 'chip'}>All categories</Link>
                    {categories.map((value) => <Link key={value} href={route('knowledge.index', { ...filters, category: value, page: undefined })}
                        aria-current={filters.category === value ? 'page' : undefined} className={`chip ${categoryColor(value)} ${filters.category === value ? 'ring-2 ring-white/70' : ''}`}>
                        {categoryLabel(value)}
                    </Link>)}
                </nav>
                <section>
                    <h2 className="text-xl font-bold">Tag directory</h2>
                    {tags.data.length === 0 && <div className="mt-3 rounded-xl border border-ink-line p-4 text-sm text-ink-muted">
                        <p>No visible tags match these filters.</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {(filters.q || filters.category || filters.needs_wiki) && <Link href={route('knowledge.index')} className="btn-ghost">Clear filters</Link>}
                            <Link href={route('knowledge.index', { needs_wiki: 1 })} className="btn-ghost">Browse tags needing a wiki</Link>
                            <Link href={route('gallery')} className="btn-ghost">Explore artwork</Link>
                        </div>
                    </div>}
                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                        {tags.data.map((tag) => (
                            <div key={tag.id} className="min-w-0 rounded-xl border border-ink-line p-3">
                                <Link href={route('knowledge.show', { type: 'tag', id: tag.id })} className="break-words font-bold text-ink-brand hover:underline">{tag.name}</Link>
                                <p className="my-2 text-sm text-ink-muted"><span className={`chip ${categoryColor(tag.category)}`}>{categoryLabel(tag.category)}</span> · {tag.posts_count} visible works{tag.locked ? ' · locked' : ''}</p>
                                {tag.posts_count === 0 && <p className="mb-2 text-xs text-ink-faint">No works are visible with your current content settings.</p>}
                                {!tag.wiki && <Link href={route('knowledge.show', { type: 'tag', id: tag.id })} className="mb-2 block text-xs font-semibold text-ink-brand hover:underline">Wiki guide needed · open page to contribute</Link>}
                                <TagFollowButton tag={tag.name} />
                            </div>
                        ))}
                    </div>
                    <Pagination data={tags} />
                </section>
                <section>
                    <h2 className="text-xl font-bold">Wiki guides</h2>
                    {articles.data.length === 0 && <div className="mt-3 rounded-xl border border-ink-line p-4 text-sm text-ink-muted"><p>No visible wiki guides match your search.</p>{canContribute ? <p className="mt-1">Add a definition or usage guide using the Start a page form below.</p> : <p className="mt-1">A verified, unrestricted account can help write community guides.</p>}</div>}
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
