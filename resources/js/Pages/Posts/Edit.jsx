import TagField from '@/Components/TagField';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { useState } from 'react';
import { MediaViewer } from '@/Components/Work';

const RATINGS = [
    { key: 'sfw', label: 'SFW' },
    { key: 'suggestive', label: 'Suggestive' },
    { key: 'explicit', label: 'Explicit · 18+' },
];

export default function PostEdit({ post }) {
    const form = useForm({
        title: post.title ?? '',
        caption: post.caption ?? '',
        tags: (post.tags ?? []).join(', '),
        base_tag_version: post.tag_version,
        rating: post.rating,
        is_original: post.is_original ?? true,
        artist_name: post.artist_name ?? '',
        source_url: post.source_url ?? '',
    });
    const [confirmDelete, setConfirmDelete] = useState(false);

    const save = (e) => {
        e.preventDefault();
        form.patch(route('posts.update', post.id));
    };

    const remove = () => {
        if (!confirmDelete) {
            setConfirmDelete(true);
            return;
        }
        form.delete(route('posts.destroy', post.id));
    };

    return (
        <AppLayout rightRail={false}>
            <Head title="Edit work" />
            <TopBar title="Edit work" back={route('posts.show', post.id)} />

            <form onSubmit={save} className="mx-auto max-w-[720px] p-3.5 md:p-5">
                <div className="mb-4 grid grid-cols-3 gap-2">
                    {post.media.map((m) => (
                        <div key={m.id} className="overflow-hidden rounded-xl border border-ink-line bg-black">
                            <MediaViewer media={m} blurred={false} />
                        </div>
                    ))}
                </div>

                <div className="space-y-3.5">
                    <div>
                        <label htmlFor="title" className="field-label">Title</label>
                        <input id="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className="field-input" maxLength={160} />
                        {form.errors.title && <p className="field-hint text-ink-expl">{form.errors.title}</p>}
                    </div>
                    <div>
                        <label htmlFor="caption" className="field-label">Caption</label>
                        <textarea id="caption" rows={4} value={form.data.caption} onChange={(e) => form.setData('caption', e.target.value)} className="field-input resize-none" maxLength={1000} />
                        <p className="field-hint">{form.data.caption.length} / 1000</p>
                    </div>
                    <TagField form={form} maxTags={50} />

                    <div className="rounded-2xl border border-ink-line bg-ink-raised/50 p-4">
                        <h2 className="text-sm font-semibold text-ink-text">Artist & Creator</h2>
                        <div className="mt-3 flex gap-2">
                            <button
                                type="button"
                                onClick={() => form.setData({ ...form.data, is_original: true, artist_name: '', source_url: '' })}
                                className={`flex-1 rounded-xl border p-2.5 text-left text-xs font-semibold ${form.data.is_original ? 'border-ink-brand bg-ink-brand/10 text-ink-text' : 'border-ink-line bg-ink-surface text-ink-muted'}`}
                            >
                                My original work
                            </button>
                            <button
                                type="button"
                                onClick={() => form.setData({ ...form.data, is_original: false })}
                                className={`flex-1 rounded-xl border p-2.5 text-left text-xs font-semibold ${!form.data.is_original ? 'border-ink-brand bg-ink-brand/10 text-ink-text' : 'border-ink-line bg-ink-surface text-ink-muted'}`}
                            >
                                Artist not on site
                            </button>
                        </div>
                        {!form.data.is_original && (
                            <div className="mt-3.5 space-y-3 border-t border-ink-line/60 pt-3">
                                <div>
                                    <label htmlFor="artist_name" className="field-label">Artist name / handle *</label>
                                    <input id="artist_name" value={form.data.artist_name} onChange={(e) => form.setData('artist_name', e.target.value)} className="field-input" maxLength={120} required={!form.data.is_original} />
                                    {form.errors.artist_name && <p className="field-hint text-ink-expl">{form.errors.artist_name}</p>}
                                </div>
                                <div>
                                    <label htmlFor="source_url" className="field-label">Original source or portfolio link</label>
                                    <input id="source_url" type="url" value={form.data.source_url} onChange={(e) => form.setData('source_url', e.target.value)} className="field-input" maxLength={500} />
                                    {form.errors.source_url && <p className="field-hint text-ink-expl">{form.errors.source_url}</p>}
                                </div>
                            </div>
                        )}
                    </div>

                    <div>
                        <span className="field-label">Rating</span>
                        <div className="flex flex-wrap gap-2">
                            {RATINGS.map((r) => (
                                <button
                                    key={r.key}
                                    type="button"
                                    onClick={() => form.setData('rating', r.key)}
                                    aria-pressed={form.data.rating === r.key}
                                    className={`min-h-11 rounded-xl border px-4 text-[13px] font-semibold ${form.data.rating === r.key ? 'border-ink-brand bg-ink-brand/10 text-ink-brand' : 'border-ink-line bg-ink-surface text-ink-muted'}`}
                                >
                                    {r.label}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-ink-line pt-4">
                    <button
                        type="button"
                        onClick={remove}
                        disabled={form.processing}
                        className={`btn min-h-[42px] rounded-full border px-4 text-[13.5px] ${confirmDelete ? 'border-ink-expl bg-ink-expl text-white' : 'border-[#6b2c3a] text-[#ff98a9] hover:bg-ink-expl/10'}`}
                    >
                        {confirmDelete ? 'Click again to delete for good' : 'Delete work'}
                    </button>
                    <div className="flex gap-2">
                        <Link href={route('posts.show', post.id)} className="btn-ghost">
                            Cancel
                        </Link>
                        <button type="submit" disabled={form.processing} className="btn-primary px-6">
                            {form.processing ? 'Saving…' : 'Save changes'}
                        </button>
                    </div>
                </div>
                {confirmDelete && (
                    <p className="mt-2 text-right text-[12px] text-ink-muted">
                        Deleting removes the post from your profile and the gallery. This cannot be undone.
                    </p>
                )}
            </form>
        </AppLayout>
    );
}
