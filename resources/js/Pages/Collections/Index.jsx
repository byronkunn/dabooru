import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { LibraryTabs } from '@/Components/DestinationTabs';
import CollectionView, { CollectionCover } from '@/Components/CollectionView';
import { WorkCell } from '@/Components/Work';
import CollectionDialog from '@/Components/CollectionDialog';
import { AdminErrors } from '@/Components/AdminUi';
import useLibraryRefresh from '@/Lib/useLibraryRefresh';

export default function CollectionsIndex({ collections, collection, allSaved, showAll, recent = [], ...view }) {
    useLibraryRefresh();
    const [creating, setCreating] = useState(false);
    const create = useForm({ name: '' });
    return <AppLayout rightRail={false}>
        <Head title={collection?.name ?? (showAll ? 'All Saved' : 'Collections')} />
        {collection || showAll ? <CollectionView collection={collection ?? allSaved} {...view} /> : <>
            <TopBar title="Library" search={false} tabs={<LibraryTabs />} actions={<button type="button" className="btn-primary" onClick={() => setCreating(true)}>New collection</button>} />
            <div className="space-y-4 p-3.5 md:p-5">
                <p className="text-sm text-ink-muted">Personal shelves for saved illustrations, videos, albums, and comics. Collections are private unless you share a link.</p>
                <nav aria-label="Saved collections" className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {[allSaved, ...collections].map((folder) => <Link key={folder.id ?? 'all'} href={folder.id ? route('collections.show', folder.id) : route('collections.index', { view: 'all' })}
                        className="flex min-w-0 items-center gap-3 rounded-xl border border-ink-line p-3 hover:border-ink-brand">
                        <CollectionCover collection={folder} className="h-16 w-16" />
                        <div className="min-w-0">
                            <h2 className="break-words font-bold">{folder.name}</h2>
                            <p className="text-sm text-ink-muted">{folder.count} {folder.count === 1 ? 'work' : 'works'}{folder.id && <> · {folder.isPublic ? 'Public' : folder.shared ? 'Shared by link' : 'Private'}</>}</p>
                        </div>
                    </Link>)}
                </nav>
                {collections.length === 0 && <p className="text-sm text-ink-muted">Create a collection, then open a work and choose Save to collection.</p>}

                {recent.length > 0 && (
                    <section aria-label="Recently saved">
                        <div className="mb-2 flex items-baseline justify-between gap-2">
                            <h2 className="text-[15px] font-bold">Recently saved</h2>
                            {allSaved?.count > 0 && (
                                <Link href={route('collections.index', { view: 'all' })} className="text-[12.5px] font-semibold text-ink-brand hover:underline">
                                    View all {allSaved.count} →
                                </Link>
                            )}
                        </div>
                        <div className="columns-2 gap-2.5 sm:columns-3 lg:columns-4">
                            {recent.map((post) => <div key={post.id} className="mb-2.5 break-inside-avoid"><WorkCell post={post} /></div>)}
                        </div>
                    </section>
                )}

                {recent.length === 0 && (
                    <p className="rounded-xl border border-dashed border-ink-line px-3 py-6 text-center text-[13px] text-ink-muted">
                        Nothing saved yet. Open a work and choose Save to keep it here.
                    </p>
                )}
            </div>
        </>}
        {creating && <CollectionDialog title="New collection" onClose={() => { if (!create.processing) setCreating(false); }}>
            <form className="space-y-3" onSubmit={(event) => {
                event.preventDefault();
                create.post(route('collections.store'), { onSuccess: () => { create.reset(); setCreating(false); } });
            }}>
                <label className="block field-label">New collection name<input className="field-input mt-1" required maxLength={80} value={create.data.name} onChange={(event) => create.setData('name', event.target.value)} /></label>
                <p className="text-sm text-ink-muted">Private by default. You can enable link sharing later.</p>
                <AdminErrors errors={create.errors} />
                <button className="btn-primary" disabled={create.processing}>Create collection</button>
            </form>
        </CollectionDialog>}
    </AppLayout>;
}
