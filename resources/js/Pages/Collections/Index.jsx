import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { WorkCell } from '@/Components/Work';

export default function CollectionsIndex({ collections, collection, posts, nextPage, previousPage }) {
    const create = useForm({ name: '' });
    const rename = useForm({ name: collection?.name ?? '' });

    return (
        <AppLayout rightRail={false}>
            <Head title={collection?.name ?? 'Saved works'} />
            <TopBar title={collection?.name ?? 'Saved works'} back={collection ? route('collections.index') : undefined} />
            <div className="space-y-5 p-3.5 md:p-5">
                <nav aria-label="Saved collections" className="flex flex-wrap gap-2">
                    <Link href={route('collections.index')} className={collection ? 'chip' : 'chip is-active'}>All saved</Link>
                    {collections.map((list) => (
                        <Link key={list.id} href={route('collections.show', list.id)} className={`chip max-w-full !whitespace-normal break-words ${collection?.id === list.id ? 'is-active' : ''}`}>
                            {list.name}
                        </Link>
                    ))}
                </nav>

                <form onSubmit={(e) => {
                    e.preventDefault();
                    create.post(route('collections.store'), { onSuccess: () => create.reset() });
                }}>
                    <label className="field-label" htmlFor="collection-name">New private collection</label>
                    <div className="flex flex-wrap gap-2">
                        <input id="collection-name" className="field-input min-w-0 flex-1" value={create.data.name} onChange={(e) => create.setData('name', e.target.value)} maxLength={80} required />
                        <button className="btn-primary" disabled={create.processing}>Create</button>
                    </div>
                    {create.errors.name && <p className="field-hint text-ink-expl">{create.errors.name}</p>}
                </form>

                {collection && (
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        rename.patch(route('collections.update', collection.id), { preserveScroll: true });
                    }}>
                        <label className="field-label" htmlFor="rename-collection">Collection name</label>
                        <div className="flex flex-wrap gap-2">
                            <input id="rename-collection" className="field-input min-w-0 flex-1" value={rename.data.name} onChange={(e) => rename.setData('name', e.target.value)} maxLength={80} required />
                            <button className="btn-ghost" disabled={rename.processing}>Rename</button>
                            <button type="button" className="btn-ghost" onClick={() => {
                                if (window.confirm('Delete this collection? Its works will stay in All saved.')) {
                                    router.delete(route('collections.destroy', collection.id));
                                }
                            }}>Delete collection</button>
                        </div>
                        {rename.errors.name && <p className="field-hint text-ink-expl">{rename.errors.name}</p>}
                    </form>
                )}

                {collection && (
                    <section className="panel space-y-2">
                        <h2 className="font-bold">Collection sharing</h2>
                        {collection.shareUrl ? <>
                            <p className="text-sm text-ink-muted">Anyone with the link can browse the works they’re allowed to see.</p>
                            <input aria-label="Collection share link" className="field-input" readOnly value={collection.shareUrl} onFocus={(event) => event.target.select()} />
                            <div className="flex flex-wrap gap-2">
                                <Link href={collection.shareUrl} className="btn-ghost">Open shared view</Link>
                                <button type="button" className="btn-ghost" onClick={() => router.patch(route('collections.share', collection.id), { shared: false }, { preserveScroll: true })}>Make private</button>
                            </div>
                        </> : <>
                            <p className="text-sm text-ink-muted">This collection is private. Create a link when you’re ready to share it.</p>
                            <button type="button" className="btn-ghost" onClick={() => router.patch(route('collections.share', collection.id), { shared: true }, { preserveScroll: true })}>Create share link</button>
                        </>}
                    </section>
                )}

                {posts.length === 0 ? (
                    <p className="py-12 text-center text-sm text-ink-muted">No works here yet. Open a work to save it or add it to a collection.</p>
                ) : (
                    <div className="columns-2 gap-3 lg:columns-3">
                        {posts.map((post) => (
                            <div key={post.id} className="mb-4 break-inside-avoid">
                                <WorkCell post={post} />
                                {collection && (
                                    <button type="button" className="btn-ghost px-2 text-xs" onClick={() => router.delete(route('collections.posts.remove', [collection.id, post.id]), { preserveScroll: true })}>
                                        Remove from collection
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                )}
                <div className="flex gap-2">
                    {previousPage && <Link className="btn-ghost" href={previousPage}>Previous</Link>}
                    {nextPage && <Link className="btn-ghost" href={nextPage}>Next</Link>}
                </div>
            </div>
        </AppLayout>
    );
}
