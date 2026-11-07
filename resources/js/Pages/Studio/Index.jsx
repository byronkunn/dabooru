import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { AdminErrors, AdminPagination } from '@/Components/AdminUi';
import TagField from '@/Components/TagField';

function Editor({ item, chapter = false }) {
    const form = useForm({
        title: item.title ?? '', caption: item.caption ?? '', rating: item.rating, tags: item.tags,
        order: item.media.map(({ id }) => id), publish: false,
    });
    const isDraft = chapter || item.is_draft;
    const canReorder = chapter || item.type === 'album';
    const media = new Map(item.media.map(({ id, preview }) => [id, preview]));

    const move = (index, delta) => {
        const order = [...form.data.order];
        [order[index], order[index + delta]] = [order[index + delta], order[index]];
        form.setData('order', order);
    };

    return (
        <form className="space-y-3" onSubmit={(event) => {
            event.preventDefault();
            const publish = event.nativeEvent.submitter?.value === 'publish';
            form.transform((data) => ({ ...data, publish }));
form.patch(
                route(chapter ? 'studio.chapters.update' : 'studio.works.update', item.id), { preserveScroll: true },
            );
        }}>
            {isDraft && <>
                <label className="block text-sm">Title<input className="field-input mt-1" maxLength={160} value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} /></label>
                {!chapter && <label className="block text-sm">Caption<textarea className="field-input mt-1" rows={2} maxLength={1000} value={form.data.caption} onChange={(event) => form.setData('caption', event.target.value)} /></label>}
                <label className="block text-sm">Rating
                    <select className="field-input mt-1" value={form.data.rating} onChange={(event) => form.setData('rating', event.target.value)}>
                        <option value="sfw">Safe for work</option><option value="suggestive">Suggestive</option><option value="explicit">Explicit</option>
                    </select>
                </label>
                <TagField form={form} />
            </>}
            <ol className="grid grid-cols-2 gap-2 md:grid-cols-3">
                {form.data.order.map((id, index) => {
                    const preview = media.get(id);
                    return <li key={id} className="min-w-0 rounded-lg border border-ink-line p-2">
                        {preview?.is_video ? <video src={preview.url} controls preload="none" className="aspect-square w-full object-contain" />
                            : preview && <a href={preview.url} target="_blank" rel="noreferrer"><img src={preview.thumb || preview.url} loading="lazy" alt={`Preview ${index + 1}`} className="aspect-square w-full rounded object-cover" /></a>}
                        <p className="mt-1 text-xs text-ink-muted">Item {index + 1}</p>
                        {canReorder && <div className="mt-1 flex flex-wrap gap-1">
                            <button type="button" className="btn-ghost px-2 text-xs" disabled={index === 0} onClick={() => move(index, -1)} aria-label={`Move item ${index + 1} earlier`}>↑</button>
                            <button type="button" className="btn-ghost px-2 text-xs" disabled={index === form.data.order.length - 1} onClick={() => move(index, 1)} aria-label={`Move item ${index + 1} later`}>↓</button>
                        </div>}
                    </li>;
                })}
            </ol>
            <AdminErrors errors={form.errors} />
            <div className="flex flex-wrap gap-2">
                <button className={isDraft ? 'btn-ghost' : 'btn-primary'} disabled={form.processing}>{isDraft ? 'Save draft' : 'Save order'}</button>
                {isDraft && <>
                    <button className="btn-primary" value="publish" disabled={form.processing}>Publish now</button>
                    <button type="button" className="btn-ghost text-ink-expl" disabled={form.processing} onClick={() => {
                        if (window.confirm('Discard this unpublished draft?')) {
                            router.delete(route(chapter ? 'studio.chapters.discard' : 'studio.works.discard', item.id), { preserveScroll: true });
                        }
                    }}>Discard draft</button>
                </>}
            </div>
        </form>
    );
}

export default function Studio({ works, chapters, claims, worksPages, chapterPages }) {
    return (
        <AppLayout rightRail={false}>
            <Head title="Creator Studio" />
            <TopBar title="Creator Studio" />
            <div className="space-y-5 p-3.5 md:p-5">
                <nav className="flex flex-wrap gap-2" aria-label="Create">
                    <Link href={route('upload.create')} className="btn-primary">Upload work</Link>
                    <Link href={route('comics.chapters.create')} className="btn-ghost">Add comic chapter</Link>
                </nav>
                {chapters.length > 0 && <section className="space-y-2">
                    <h2 className="font-bold">Chapter drafts</h2>
                    {chapters.map((item) => <details className="panel" key={item.id}><summary className="cursor-pointer break-words">{item.series} · Chapter {item.number}{item.title && ` · ${item.title}`}</summary>
                        <div className="mt-3"><Editor item={item} chapter /></div>
                    </details>)}
                    <AdminPagination {...chapterPages} />
                </section>}
                <section className="space-y-2">
                    <h2 className="font-bold">Your works</h2>
                    {works.map((item) => <article className="panel space-y-2" key={item.id}>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="break-words font-bold">{item.title || 'Untitled'} <span className="text-xs font-normal text-ink-muted">· {item.is_draft ? 'Private draft' : item.type}</span></p>
                            {!item.is_draft && <Link className="link-brand text-sm" href={route('posts.show', item.id)}>Open work</Link>}
                        </div>
                        {!item.is_draft && <p className="text-sm text-ink-muted">{item.views.toLocaleString()} views · {item.bookmarks_count.toLocaleString()} saves</p>}
                        {(item.is_draft || item.type === 'album') && <details>
                            <summary className="cursor-pointer text-sm text-ink-brand">{item.is_draft ? 'Edit and preview draft' : 'Reorder album'}</summary>
                            <div className="mt-3"><Editor key={`${item.id}-${item.is_draft}`} item={item} /></div>
                        </details>}
                    </article>)}
                    {works.length === 0 && <p className="text-sm text-ink-muted">Upload a work to start.</p>}
                    <AdminPagination {...worksPages} />
                </section>
                {claims.length > 0 && <section className="space-y-2">
                    <h2 className="font-bold">Your artist credit claims</h2>
                    {claims.map((claim) => <div key={claim.id} className="panel break-words text-sm">
                        {claim.url ? <Link href={claim.url} className="link-brand">{claim.title || 'Untitled work'}</Link> : 'Unavailable work'} · {claim.status}
                        {claim.reason && <p className="mt-1 text-ink-muted">{claim.reason}</p>}
                    </div>)}
                </section>}
            </div>
        </AppLayout>
    );
}
