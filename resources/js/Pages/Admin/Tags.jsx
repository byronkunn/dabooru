import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Errors, Pagination, TagPicker } from '@/Components/KnowledgeUi';
import { categoryColor, categoryLabel } from '@/Lib/tagCategories';

function TagControls({ tag, categories }) {
    const lock = useForm({ locked: !tag.locked });
    const review = useForm({ category: tag.category, is_approved: tag.is_approved });
    const merge = useForm({ destination_id: '', destination_version: 0, base_version: tag.version, reason: '' });
    const [destination, setDestination] = useState([]);
    return (
        <article className="panel min-w-0 space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <h2 className="break-words font-bold">{tag.name}</h2>
                <span className={`chip ${categoryColor(tag.category)}`}>{categoryLabel(tag.category)}</span>
                {!tag.is_approved && <span className="chip border border-amber-400/40 text-amber-300">Pending approval</span>}
                <span className="text-sm text-ink-muted">{tag.posts_count} published works · {tag.locked ? 'Locked' : 'Unlocked'}</span>
            </div>
            <form className="flex flex-wrap items-end gap-2" onSubmit={(event) => {
                event.preventDefault();
                review.transform((data) => ({ ...data, is_approved: true }));
                review.post(route('admin.tags.review', tag.id), { preserveScroll: true });
            }}>
                <label className="min-w-48 flex-1 text-sm">Category<select className="field-input mt-1" value={review.data.category} onChange={(event) => review.setData('category', event.target.value)}>
                    {categories.map((value) => <option key={value} value={value}>{categoryLabel(value)}</option>)}
                </select></label>
                <button className="btn-primary" disabled={review.processing}>{tag.is_approved ? 'Save category' : 'Approve tag'}</button>
            </form>
            <Errors errors={review.errors} />
            <div className="flex flex-wrap gap-2">
                <Link href={route('knowledge.show', { type: 'tag', id: tag.id, tab: 'edit' })} className="btn-primary">Edit tag</Link>
                <Link href={route('knowledge.show', { type: 'tag', id: tag.id, tab: 'history' })} className="btn-ghost">History</Link>
                <button type="button" className="btn-ghost" disabled={lock.processing} onClick={() => {
                    lock.transform(() => ({ locked: !tag.locked }));
                    lock.post(route('admin.knowledge.lock', { type: 'tag', id: tag.id }), { preserveScroll: true });
                }}>{tag.locked ? 'Unlock tag' : 'Lock tag'}</button>
            </div>
            <Errors errors={lock.errors} />
            <details>
                <summary className="min-h-11 cursor-pointer py-2 font-semibold">Merge this tag</summary>
                <form className="space-y-3" onSubmit={(event) => {
                    event.preventDefault();
                    const name = destination[0]?.name;
                    if (name && window.confirm(`Merge "${tag.name}" into "${name}"? All affected media and followers will move. Old names will redirect; the destination definition and category stay unchanged. This cannot be undone with a single history revert.`)) {
                        merge.transform((data) => ({ ...data, base_version: tag.version }));
                        merge.post(route('admin.tags.merge', tag.id));
                    }
                }}>
                    <p className="text-sm text-ink-muted">Choose the canonical tag to keep. Media tags are deduplicated, followers are retained, and old names redirect. Related or implied relationships on the source must be reviewed and removed first. History records the changes; merges cannot be undone with a single history revert.</p>
                    <TagPicker label="Keep this canonical tag" selected={destination} onChange={(selected) => {
                        const choice = selected.slice(-1);
                        setDestination(choice);
                        merge.setData({ ...merge.data, destination_id: choice[0]?.id ?? '', destination_version: choice[0]?.version ?? 0 });
                    }} />
                    <label className="block text-sm">Merge reason<input className="field-input" required minLength={3} maxLength={500}
                        value={merge.data.reason} onChange={(event) => merge.setData('reason', event.target.value)} /></label>
                    <Errors errors={merge.errors} />
                    <button className="btn-primary" disabled={merge.processing || !destination.length || destination[0].id === tag.id}>Merge tags</button>
                </form>
            </details>
        </article>
    );
}

export default function Tags({ tags, categories, filters }) {
    const [q, setQ] = useState(filters.q ?? '');
    const [category, setCategory] = useState(filters.category ?? '');
    return (
        <AppLayout admin rightRail={false}>
            <Head title="Manage tags" />
            <TopBar title="Tags & merges" />
            <div className="space-y-5 p-4 md:p-5">
                <p className="text-sm text-ink-muted">New tags stay out of public search and tag suggestions until an administrator approves them. Choose the category before approving. Existing tags remain available.</p>
                <Link href={route('knowledge.index')} className="btn-ghost">Public tag directory</Link>
                <form className="space-y-3" onSubmit={(event) => {
                    event.preventDefault();
                    router.get(route('admin.tags.index'), { q, category: category || undefined });
                }}>
                    <label className="block">Find tags<input className="field-input" maxLength={160} value={q} onChange={(event) => setQ(event.target.value)} /></label>
                    <label className="block">Category<select className="field-input" value={category} onChange={(event) => setCategory(event.target.value)}>
                        <option value="">All categories</option>
                        {categories.map((value) => <option key={value} value={value}>{categoryLabel(value)}</option>)}
                    </select></label>
                    <button className="btn-primary">Find tags</button>
                </form>
                {tags.data.map((tag) => <TagControls key={`${tag.id}-${tag.version}`} tag={tag} categories={categories} />)}
                {tags.data.length === 0 && <p className="text-ink-muted">No tags match these filters.</p>}
                <Pagination data={tags} />
            </div>
        </AppLayout>
    );
}
