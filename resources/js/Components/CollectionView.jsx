import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { TopBar } from '@/Layouts/AppLayout';
import { WorkCell } from '@/Components/Work';
import MediaThumbnail from '@/Components/MediaThumbnail';
import { AdminErrors, AdminPagination } from '@/Components/AdminUi';
import CollectionDialog from '@/Components/CollectionDialog';
import SaveWorkActions from '@/Components/SaveWorkActions';
import LibraryMutedTags from '@/Components/LibraryMutedTags';

export function CollectionCover({ collection, className = 'h-12 w-12' }) {
    const media = collection.cover?.media?.[0];
    return <div className={`${className} shrink-0 overflow-hidden rounded-lg border border-ink-line bg-ink-raised`}>
        {media ? <MediaThumbnail media={media} className="h-full w-full object-cover" /> : <span className="grid h-full place-items-center text-xs text-ink-muted">Empty</span>}
    </div>;
}

function CollectionEditor({ collection, items, hiddenBookmarks }) {
    const metadata = useForm({ name: collection.name, description: collection.description ?? '', cover_post_id: collection.cover_post_id ?? '' });
    const ordering = useForm({ order: items.map((item) => item.id) });
    const removal = useForm({});
    const deletion = useForm({});
    const dragged = useRef(null);
    const itemIds = items.map((item) => item.id).join(',');
    useEffect(() => {
        const ids = items.map((item) => item.id);
        const retained = ordering.data.order.filter((id) => ids.includes(id));
        ordering.setData('order', [...retained, ...ids.filter((id) => !retained.includes(id))]);
        if (metadata.data.cover_post_id && !ids.includes(Number(metadata.data.cover_post_id))) metadata.setData('cover_post_id', '');
    }, [itemIds]);
    const move = (id, destination) => {
        const current = [...ordering.data.order];
        const from = current.indexOf(id);
        if (from < 0 || destination < 0 || destination >= current.length) return;
        current.splice(from, 1);
        current.splice(destination, 0, id);
        ordering.setData('order', current);
    };
    return <section aria-label="Edit collection" className="space-y-4">
        <form className="panel space-y-3" onSubmit={(event) => {
            event.preventDefault();
            metadata.transform(({ cover_post_id, ...details }) =>
                String(cover_post_id) === String(collection.cover_post_id ?? '')
                    ? details : { ...details, cover_post_id: cover_post_id || null });
            metadata.patch(route('collections.update', collection.id), { preserveScroll: true });
        }}>
            <h2 className="font-bold">Edit collection</h2>
            <label className="block field-label">Collection name<input className="field-input mt-1" required maxLength={80} value={metadata.data.name} onChange={(event) => metadata.setData('name', event.target.value)} /></label>
            <label className="block field-label">Description<textarea className="field-input mt-1" rows={2} maxLength={1000} value={metadata.data.description} onChange={(event) => metadata.setData('description', event.target.value)} /></label>
            <label className="block field-label">Collection cover<select className="field-input mt-1" value={metadata.data.cover_post_id} onChange={(event) => metadata.setData('cover_post_id', event.target.value)}>
                <option value="">Automatic cover</option>
                {items.map((item) => <option key={item.id} value={item.id}>{item.title || `Work ${item.id}`}</option>)}
            </select></label>
            <AdminErrors errors={metadata.errors} />
            <button className="btn-primary" disabled={metadata.processing}>Save details</button>
        </form>
        <form className="space-y-3" onSubmit={(event) => {
            event.preventDefault();
            ordering.patch(route('collections.reorder', collection.id), { preserveScroll: true });
        }}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-bold">Custom order</h2>
                <button className="btn-primary" disabled={ordering.processing || items.length === 0}>Save custom order</button>
            </div>
            <p className="text-sm text-ink-muted">Drag works or use Move up / Move down. Hidden works keep their places.</p>
            <ol className="space-y-2">
                {ordering.data.order.map((id, index) => {
                    const item = items.find((post) => post.id === id);
                    if (!item) return null;
                    return <li key={id} data-order-id={id} draggable={!ordering.processing && !removal.processing} onDragStart={(event) => {
                        dragged.current = id;
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', String(id));
                    }} onDragEnd={() => { dragged.current = null; }} onDragOver={(event) => event.preventDefault()}
                        onDrop={(event) => { event.preventDefault(); if (dragged.current !== null) move(dragged.current, index); dragged.current = null; }}
                        className="flex flex-wrap items-center gap-2 rounded-xl border border-ink-line p-2">
                        <button type="button" className="btn-ghost touch-none cursor-grab text-xs active:cursor-grabbing"
                            aria-label={`Drag to reorder: ${item.title || id}`} disabled={ordering.processing || removal.processing}
                            onPointerDown={(event) => {
                                event.preventDefault();
                                dragged.current = id;
                                event.currentTarget.setPointerCapture(event.pointerId);
                            }} onPointerCancel={() => { dragged.current = null; }} onPointerUp={(event) => {
                                const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-order-id]');
                                if (target && event.currentTarget.closest('ol')?.contains(target)) {
                                    move(id, ordering.data.order.indexOf(Number(target.dataset.orderId)));
                                }
                                dragged.current = null;
                                event.currentTarget.releasePointerCapture(event.pointerId);
                            }}>Drag</button>
                        <CollectionCover collection={{ cover: item }} className="h-10 w-10" />
                        <span className="min-w-0 flex-1 break-words text-sm">{index + 1}. {item.title || 'Untitled work'}</span>
                        <div className="flex w-full flex-wrap gap-1 sm:w-auto">
                            <button type="button" className="btn-ghost text-xs" aria-label={`Move up: ${item.title || id}`} disabled={index === 0 || ordering.processing} onClick={() => move(id, index - 1)}>Move up</button>
                            <button type="button" className="btn-ghost text-xs" aria-label={`Move down: ${item.title || id}`} disabled={index === ordering.data.order.length - 1 || ordering.processing} onClick={() => move(id, index + 1)}>Move down</button>
                            <button type="button" className="btn-ghost text-xs" disabled={removal.processing} onClick={() => {
                                removal.delete(route('collections.posts.remove', [collection.id, id]), { preserveScroll: true });
                            }}>Remove</button>
                        </div>
                    </li>;
                })}
            </ol>
            <AdminErrors errors={ordering.errors} />
        </form>
        {hiddenBookmarks.length > 0 && <section aria-label="Hidden works" className="panel space-y-2">
            <h2 className="font-bold">Hidden works ({hiddenBookmarks.length})</h2>
            <p className="text-sm text-ink-muted">These works are unavailable or hidden by your settings. You can still remove them from the collection.</p>
            <ul className="space-y-2">
                {hiddenBookmarks.map((bookmark, index) => <li key={bookmark} className="flex items-center justify-between gap-2">
                    <span className="text-sm">Hidden work {index + 1}</span>
                    <button type="button" className="btn-ghost" aria-label={`Remove hidden work ${index + 1}`}
                        disabled={removal.processing} onClick={() => {
                            removal.delete(route('collections.bookmarks.remove', [collection.id, bookmark]), { preserveScroll: true });
                        }}>Remove</button>
                </li>)}
            </ul>
        </section>}
        <AdminErrors errors={removal.errors} />
        <button type="button" className="btn-ghost text-ink-expl" disabled={deletion.processing} onClick={() => {
            if (window.confirm('Delete this collection? Its works will remain in All Saved.')) deletion.delete(route('collections.destroy', collection.id));
        }}>Delete collection</button>
        <AdminErrors errors={deletion.errors} />
    </section>;
}

export default function CollectionView({ collection, posts, order, mutedTags, nextPage, previousPage, owner, ownerUrl, editing = false, editItems = [], hiddenBookmarks = [], readOnly = false }) {
    const { url } = usePage();
    const [sharing, setSharing] = useState(false);
    const share = useForm({});
    const [copyError, setCopyError] = useState('');
    const [copied, setCopied] = useState(false);
    const back = readOnly
        ? route('profile.show', { username: owner, tab: collection.isPublic ? 'collections' : undefined })
        : route('collections.index');
    const changeView = (changes) => {
        const target = new URL(url, window.location.origin);
        target.searchParams.delete('page');
        Object.entries(changes).forEach(([key, value]) => { if (value === null) target.searchParams.delete(key); else target.searchParams.set(key, value); });
        router.get(target.pathname + target.search, {}, { preserveScroll: true });
    };
    return <>
        <TopBar title={collection.name} subtitle={`${collection.count} ${collection.count === 1 ? 'work' : 'works'}`} back={back} backLabel={readOnly ? 'Back to profile' : 'Back to Library'} search={false}
            actions={!readOnly && collection.id ? <>
                <button type="button" className="btn-ghost" onClick={() => setSharing(true)}>Share</button>
                <button type="button" className="btn-ghost" onClick={() => changeView({ edit: editing ? null : '1' })}>{editing ? 'Done' : 'Edit'}</button>
            </> : ownerUrl ? <Link href={ownerUrl} className="btn-ghost">Edit collection</Link> : null} />
        <div className="space-y-3 p-3.5 md:p-5">
            <header className="flex items-start gap-3">
                <CollectionCover collection={collection} />
                <div className="min-w-0">
                    <p className="text-sm text-ink-muted">{collection.count} {collection.count === 1 ? 'work' : 'works'} {collection.id && <> · {collection.isPublic ? 'Public' : collection.shared ? 'Shared by link' : 'Private'}</>}
                        {owner && <> · by <Link href={route('profile.show', owner)} className="link-brand">@{owner}</Link></>}</p>
                    {collection.description && <p className="mt-1 whitespace-pre-wrap break-words text-sm">{collection.description}</p>}
                </div>
            </header>
            <div className="flex flex-wrap items-end gap-2">
                {collection.id && <label className="field-label">Order
                    <select className="field-input mt-1 !w-auto" value={order} onChange={(event) => changeView({ order: event.target.value })}>
                        <option value="recent">Recently added</option><option value="custom">Custom order</option>
                    </select>
                </label>}
                <LibraryMutedTags tags={mutedTags} />
            </div>
            {editing && <CollectionEditor key={collection.id} collection={collection} items={editItems} hiddenBookmarks={hiddenBookmarks} />}
            {posts.length === 0 ? <div className="py-10 text-center">
                <p className="text-sm text-ink-muted">{collection.count ? 'No works on this page.' : 'No visible works here yet. Open a work and choose Save to collection to add it.'}</p>
                <Link href={route('gallery')} className="btn-primary mt-3">Browse gallery</Link>
            </div> : <div aria-label="Collection works" className="grid grid-cols-2 items-start gap-3 sm:grid-cols-3 xl:grid-cols-4">
                {posts.map((post) => <div key={post.id} className="min-w-0">
                    <WorkCell post={post} />
                    <SaveWorkActions postId={post.id} saved={post.saved} compact />
                </div>)}
            </div>}
            <AdminPagination {...{ nextPage, previousPage }} />
        </div>
        {sharing && <CollectionDialog title="Share collection" onClose={() => setSharing(false)}>
            <p className="mb-3 text-sm text-ink-muted">Only works each viewer is allowed to see appear in the collection. Editing stays with you. Public collections appear on your profile; link sharing lets anyone with the link view a private collection.</p>
            <div className="mb-4 space-y-2">
                <p className="font-semibold">{collection.isPublic ? 'Public on your profile' : collection.shared ? 'Shared by link' : 'Private collection'}</p>
                {collection.publicUrl && <Link href={collection.publicUrl} className="link-brand block">Open public collection</Link>}
                {collection.isPublic && collection.shared && <p className="text-sm text-ink-muted">Making this collection private also turns off its share link.</p>}
                <button type="button" className="btn-primary" disabled={share.processing} onClick={() => {
                    share.transform(() => ({ is_public: !collection.isPublic }));
                    share.patch(route('collections.visibility', collection.id), { preserveScroll: true });
                }}>{collection.isPublic ? 'Make private' : 'Make public'}</button>
            </div>
            {collection.shareUrl && <>
                <label className="block field-label">Collection share link<input className="field-input mt-1" readOnly value={collection.shareUrl} onFocus={(event) => event.target.select()} /></label>
                <div className="my-3 flex flex-wrap gap-2">
                    <Link href={collection.shareUrl} className="btn-ghost">Open shared view</Link>
                    <button type="button" className="btn-ghost" onClick={async () => {
                        setCopyError('');
                        try { await navigator.clipboard.writeText(collection.shareUrl); setCopied(true); }
                        catch { setCopyError('Could not copy. Select and copy the link above.'); }
                    }}>{copied ? 'Copied' : 'Copy link'}</button>
                </div>
            </>}
            {copyError && <p role="alert" className="text-sm text-ink-expl">{copyError}</p>}
            <button type="button" className="btn-primary" disabled={share.processing} onClick={() => {
                share.transform(() => ({ shared: !collection.shared }));
                share.patch(route('collections.share', collection.id), { preserveScroll: true });
            }}>{collection.shared ? 'Turn off link sharing' : 'Turn on link sharing'}</button>
            <AdminErrors errors={share.errors} />
        </CollectionDialog>}
    </>;
}
