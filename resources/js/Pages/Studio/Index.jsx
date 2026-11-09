import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { AdminErrors, AdminPagination } from '@/Components/AdminUi';
import TagField from '@/Components/TagField';
import StudioDashboard, { StudioTabs } from '@/Components/StudioDashboard';
import StudioPlanner from '@/Components/StudioPlanner';

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


/**
 * Select several of your own works and change them together.
 *
 * Everything goes through one request that can run in preview mode, so the list
 * shown before confirming is exactly what will be touched.
 */
function BulkTools({ works, comics }) {
    const [selected, setSelected] = useState([]);
    const [action, setAction] = useState('add_tags');
    const [tags, setTags] = useState('');
    const [rating, setRating] = useState('sfw');
    const [comicId, setComicId] = useState(comics[0]?.id ?? '');
    const [chapterId, setChapterId] = useState(comics[0]?.chapters?.[0]?.id ?? '');
    const [preview, setPreview] = useState(null);
    const form = useForm({});

    const chapters = comics.find((c) => c.id === Number(comicId))?.chapters ?? [];
    const toggle = (id) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    const allOn = works.length > 0 && selected.length === works.length;

    const payload = () => ({
        works: selected,
        action,
        tags,
        rating,
        comic_id: action === 'series' ? Number(comicId) : null,
        chapter_id: action === 'series' ? Number(chapterId) : null,
    });

    const [busy, setBusy] = useState(false);

    // preview is a read-only JSON call so the current selection survives
    const runPreview = async () => {
        setBusy(true);
        try {
            const { data } = await window.axios.post(route('studio.bulk'), { ...payload(), preview: true });
            setPreview(data);
        } catch {
            setPreview(null);
        } finally {
            setBusy(false);
        }
    };

    const apply = () => form.post(route('studio.bulk'), {
        data: { ...payload(), preview: false },
        preserveScroll: true,
        onSuccess: () => { setPreview(null); setSelected([]); },
    });

    if (works.length === 0) return null;

    return (
        <div className="panel space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <p className="text-sm font-bold">Bulk edit</p>
                <button type="button" className="link-brand text-[12.5px]" onClick={() => setSelected(allOn ? [] : works.map((w) => w.id))}>
                    {allOn ? 'Clear selection' : `Select all on page (${works.length})`}
                </button>
                <span className="ml-auto text-[12.5px] text-ink-muted">{selected.length} selected</span>
            </div>

            <div className="max-h-[220px] space-y-1 overflow-y-auto rounded-xl border border-ink-line p-1.5">
                {works.map((work) => (
                    <label key={work.id} className="flex cursor-pointer items-center gap-2.5 rounded-lg px-1.5 py-1 hover:bg-ink-raised">
                        <input type="checkbox" checked={selected.includes(work.id)} onChange={() => toggle(work.id)} className="h-4 w-4 shrink-0" />
                        <span className="min-w-0 flex-1 truncate text-[13px]">{work.title || 'Untitled'} <span className="text-ink-faint">· {work.rating}</span></span>
                    </label>
                ))}
            </div>

            <div className="flex flex-wrap items-end gap-2">
                <label className="text-[12.5px]">
                    Change
                    <select value={action} onChange={(e) => { setAction(e.target.value); setPreview(null); }} className="field-input mt-1 min-h-[38px] text-[13px]" aria-label="Bulk action">
                        <option value="add_tags">Add tags</option>
                        <option value="remove_tags">Remove tags</option>
                        <option value="rating">Change rating</option>
                        <option value="series">Move into a series</option>
                    </select>
                </label>

                {(action === 'add_tags' || action === 'remove_tags') && (
                    <label className="min-w-[180px] flex-1 text-[12.5px]">
                        Tags
                        <input value={tags} onChange={(e) => { setTags(e.target.value); setPreview(null); }} placeholder="comma, separated" className="field-input mt-1 min-h-[38px]" />
                    </label>
                )}

                {action === 'rating' && (
                    <label className="text-[12.5px]">
                        New rating
                        <select value={rating} onChange={(e) => { setRating(e.target.value); setPreview(null); }} className="field-input mt-1 min-h-[38px] text-[13px]" aria-label="New rating">
                            <option value="sfw">SFW</option>
                            <option value="suggestive">Suggestive</option>
                            <option value="explicit">Explicit</option>
                        </select>
                    </label>
                )}

                {action === 'series' && (
                    <>
                        <label className="text-[12.5px]">
                            Series
                            <select value={comicId} onChange={(e) => { setComicId(e.target.value); setChapterId(''); setPreview(null); }} className="field-input mt-1 min-h-[38px] text-[13px]" aria-label="Series">
                                {comics.map((c) => <option key={c.id} value={c.id}>{c.title}</option>)}
                            </select>
                        </label>
                        <label className="text-[12.5px]">
                            Chapter
                            <select value={chapterId} onChange={(e) => { setChapterId(e.target.value); setPreview(null); }} className="field-input mt-1 min-h-[38px] text-[13px]" aria-label="Chapter">
                                {chapters.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                            </select>
                        </label>
                    </>
                )}

                <button type="button" disabled={selected.length === 0 || busy || form.processing} onClick={runPreview} className="btn-ghost min-h-[38px]">
                    {busy ? 'Checking…' : 'Preview'}
                </button>
            </div>

            {action === 'series' && comics.length === 0 && (
                <p className="text-[12.5px] text-ink-muted">You have no series yet. Create one first.</p>
            )}

            {preview && (
                <div className="rounded-xl border border-ink-brand/40 bg-ink-brand/5 p-3">
                    <p className="text-[13px] font-bold">
                        {preview.affected} of {preview.items.length} work{preview.items.length === 1 ? '' : 's'} will change
                    </p>
                    <ul className="mt-2 space-y-1">
                        {preview.items.map((item) => (
                            <li key={item.id} className="flex items-center gap-2 text-[12.5px]">
                                {item.thumb && <img src={item.thumb} alt="" className="h-8 w-8 shrink-0 rounded object-cover" />}
                                <span className="min-w-0 flex-1 truncate">{item.title}</span>
                                <span className={item.affected ? 'shrink-0 text-ink-brand' : 'shrink-0 text-ink-faint'}>
                                    {item.affected ? item.changes.join(' · ') : 'no change'}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-2 flex gap-2">
                        <button type="button" disabled={preview.affected === 0 || form.processing} onClick={apply} className="btn-primary min-h-[36px] text-[12.5px]">
                            Apply to {preview.affected} work{preview.affected === 1 ? '' : 's'}
                        </button>
                        <button type="button" onClick={() => setPreview(null)} className="btn-ghost min-h-[36px] text-[12.5px]">Cancel</button>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function Studio({ studioFilters, works = [], chapters = [], claims = [], worksPages = {}, chapterPages = {}, analytics = null, performance = [], performancePages = {}, comicAnalytics = [], comicPages = {}, comicTrackingSince = null, plans = [], planStage = null, planCounts = {}, planPages = {}, draftWorks = [], draftChapters = [], comics = [] }) {
    return (
        <AppLayout rightRail={false}>
            <Head title="Creator Studio" />
            <TopBar title="Creator Studio" search={false} tabs={<StudioTabs filters={studioFilters} />} />
            <div className="space-y-5 p-3.5 md:p-5">
                <nav className="flex flex-wrap gap-2" aria-label="Create">
                    <Link href={route('upload.create')} className="btn-primary">Upload work</Link>
                    <Link href={route('comics.chapters.create')} className="btn-ghost">Add comic chapter</Link>
                </nav>
                <StudioDashboard filters={studioFilters} analytics={analytics} performance={performance} performancePages={performancePages} comicAnalytics={comicAnalytics} comicPages={comicPages} comicTrackingSince={comicTrackingSince} />
                {studioFilters.tab === 'planner' && <StudioPlanner filters={studioFilters} plans={plans} planStage={planStage} planCounts={planCounts} planPages={planPages} draftWorks={draftWorks} draftChapters={draftChapters} />}
                {studioFilters.tab === 'comics' && chapters.length > 0 && <section className="space-y-2">
                    <h2 className="font-bold">Chapter drafts</h2>
                    {chapters.map((item) => <details className="panel" key={item.id}><summary className="cursor-pointer break-words">{item.series} · Chapter {item.number}{item.title && ` · ${item.title}`}</summary>
                        <div className="mt-3"><Editor item={item} chapter /></div>
                    </details>)}
                    <AdminPagination {...chapterPages} />
                </section>}
                {studioFilters.tab === 'posts' && <section className="space-y-2">
                    <h2 className="font-bold">Your works</h2>
                    <BulkTools works={works} comics={comics ?? []} />
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
                </section>}
                {studioFilters.tab === 'overview' && claims.length > 0 && <section className="space-y-2">
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
