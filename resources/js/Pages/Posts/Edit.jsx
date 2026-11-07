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
        rating: post.rating,
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
