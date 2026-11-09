import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Icon } from '@/Components/Icons';
import { Errors, TagPicker } from '@/Components/KnowledgeUi';
import TagFollowButton from '@/Components/TagFollowButton';
import { categoryColor, categoryLabel } from '@/Lib/tagCategories';

function groupedTags(post) {
    const groups = {};
    for (const tag of post.tags ?? []) {
        const category = post.tagCategories?.[tag] ?? 'general';
        (groups[category] ??= []).push(tag);
    }
    return Object.entries(groups).sort(([a], [b]) => categoryLabel(a).localeCompare(categoryLabel(b)));
}

function TagLinks({ tags, category = 'general' }) {
    return tags.map((tag) => (
        <Link key={tag} href={route('gallery', { tag, window: 'all' })} title={categoryLabel(category)} className={`chip max-w-full !whitespace-normal break-words ${categoryColor(category)}`}>#{tag}</Link>
    ));
}

function TagsDialog({ post, editing, onClose }) {
    const { auth } = usePage().props;
    const [selected, setSelected] = useState([]);
    const form = useForm({ summary: '', base_version: editing.version });
    const tags = post.tags ?? [];
    const additions = selected.filter((tag) => !tags.includes(tag.name));
    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, tags: [...tags, ...additions.map((tag) => tag.name)], base_version: editing.version }));
        form.post(route('knowledge.propose', { type: 'post', id: post.id }), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onClose={() => !form.processing && onClose()} className="fixed inset-0 z-[80]">
            <div className="fixed inset-0 bg-black/70" aria-hidden="true" />
            <div className="fixed inset-0 flex items-center justify-center p-4">
                <DialogPanel className="flex max-h-[85dvh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-ink-line bg-ink-surface">
                    <div className="flex items-center gap-3 border-b border-ink-line p-4">
                        <div className="min-w-0">
                            <DialogTitle className="font-bold">All tags ({tags.length})</DialogTitle>
                            <p className="truncate text-xs text-ink-muted">{post.title || 'This work'}</p>
                        </div>
                        <button type="button" onClick={onClose} disabled={form.processing} aria-label="Close tags" className="btn-icon-flat ml-auto shrink-0"><Icon name="close" /></button>
                    </div>
                    <div className="overflow-y-auto p-4">
                        <nav aria-label="All work tags" className="space-y-4">
                            {groupedTags(post).map(([category, names]) => <section key={category} aria-label={categoryLabel(category)} className="space-y-2">
                                <h3 className="text-sm font-bold">{categoryLabel(category)} <span className="text-ink-muted">({names.length})</span></h3>
                                {names.map((tag) => <div key={tag} className="flex flex-wrap items-center justify-between gap-2"><TagLinks tags={[tag]} category={category} /><TagFollowButton tag={tag} /></div>)}
                            </section>)}
                        </nav>
                        {tags.length === 0 && <p className="text-sm text-ink-muted">No tags yet.</p>}
                        {editing.canContribute ? (
                            <form onSubmit={submit} className="mt-4 border-t border-ink-line pt-3">
                                <h3 className="font-semibold">Add tags to this work</h3>
                                <p className="mt-1 text-xs text-ink-muted">{editing.trusted ? 'Your additions publish immediately.' : 'Your additions are submitted for moderator review.'} Existing tags are kept.</p>
                                <TagPicker namesOnly label="Tags to add" selected={selected} onChange={setSelected} />
                                <label className="block text-sm">Reason for adding tags
                                    <input className="field-input mt-1" required minLength={3} maxLength={500} value={form.data.summary} onChange={(event) => form.setData('summary', event.target.value)} />
                                </label>
                                <Errors errors={form.errors} />
                                <button className="btn-primary mt-3" disabled={form.processing || additions.length === 0 || tags.length + additions.length > 50}>{form.processing ? 'Submitting...' : editing.trusted ? 'Add tags' : 'Submit tags for review'}</button>
                                {tags.length + additions.length > 50 && <p role="alert" className="mt-2 text-sm text-red-300">A work can have up to 50 tags.</p>}
                            </form>
                        ) : (
                            <p className="mt-4 text-sm text-ink-muted">{auth.user ? 'Tag contributions require a verified, unrestricted account.' : <Link href={route('login')} className="text-ink-brand hover:underline">Log in to suggest tags.</Link>}</p>
                        )}
                        <Link href={route('knowledge.show', { type: 'post', id: post.id })} className="mt-4 inline-block text-sm text-ink-brand hover:underline">View tag history</Link>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    );
}

export default function PostTags({ post, editing }) {
    const [open, setOpen] = useState(false);
    const tags = post.tags ?? [];
    return (
        <section className="mt-3" aria-label="Work tags">
            <nav className="flex flex-wrap gap-2" aria-label="Work tag preview">
                {tags.slice(0, 5).map((tag) => <TagLinks key={tag} tags={[tag]} category={post.tagCategories?.[tag] ?? 'general'} />)}
            </nav>
            <button type="button" onClick={() => setOpen(true)} aria-haspopup="dialog" className="mt-2 min-h-11 text-sm font-semibold text-ink-brand hover:underline">
                {tags.length > 5 ? `View all ${tags.length} tags / add tags` : 'View tags / add tags'}
            </button>
            {open && <TagsDialog post={post} editing={editing} onClose={() => setOpen(false)} />}
        </section>
    );
}
