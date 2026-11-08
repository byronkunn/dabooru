import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { ChatDialog, FormErrors } from './Inbox';

export default function Stickers({ packs, works }) {
    const [create, setCreate] = useState(false);
    const form = useForm({ title: '', post_ids: [] });
    return (
        <AppLayout rightRail={false}>
            <Head title="Sticker packs" />
            <TopBar title="Sticker packs" back={route('messages.index')} actions={
                <button type="button" className="btn-primary text-xs" onClick={() => setCreate(true)}>New pack</button>
            } siteActions={false} />
            <div className="mx-auto max-w-4xl space-y-4 p-4">
                {!packs.length && <p className="py-8 text-center text-sm text-ink-muted">No sticker packs here yet.</p>}
                {packs.map((pack) => <section key={pack.id} className="space-y-3 rounded-xl border border-ink-line p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="min-w-0 flex-1 break-words font-bold">{pack.title}</h2>
                        <Link href={route('profile.show', pack.artist)} className="link-brand text-sm">@{pack.artist}</Link>
                        {pack.mine && <button type="button" className="btn-ghost text-xs text-ink-expl" onClick={() => {
                            if (window.confirm('Remove this sticker pack?')) router.delete(route('messages.stickers.destroy', pack.id));
                        }}>Remove pack</button>}
                    </div>
                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-6">
                        {pack.stickers.map((entry) => <img key={entry.id} src={entry.thumbnail} alt={entry.title} loading="lazy"
                            className="aspect-square w-full rounded-lg bg-ink-raised object-contain" />)}
                    </div>
                </section>)}
            </div>
            {create && <ChatDialog title="Publish a sticker pack" onClose={() => setCreate(false)}>
                <form className="space-y-3" onSubmit={(event) => {
                    event.preventDefault();
                    form.post(route('messages.stickers.store'), { onSuccess: () => { form.reset(); setCreate(false); } });
                }}>
                    <label className="block text-sm">Name
                        <input required className="field-input mt-1" maxLength={80} value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                    </label>
                    <p className="text-sm text-ink-muted">Choose up to 16 of your published, original, SFW illustrations.</p>
                    <div className="max-h-52 space-y-1 overflow-y-auto">
                        {works.length ? works.map((work) => {
                            const selected = form.data.post_ids.includes(work.id);
                            return <label key={work.id} className="flex items-center gap-2 rounded-lg bg-ink-raised p-2 text-sm">
                                <input type="checkbox" checked={selected} disabled={!selected && form.data.post_ids.length >= 16}
                                    onChange={() => form.setData('post_ids', selected ? form.data.post_ids.filter((id) => id !== work.id) : [...form.data.post_ids, work.id])} />
                                {work.title || `Work #${work.id}`}
                            </label>;
                        }) : <p className="text-sm">Publish an original SFW illustration to create a pack.</p>}
                    </div>
                    <FormErrors errors={form.errors} />
                    <button type="submit" className="btn-primary" disabled={form.processing || !form.data.post_ids.length}>Publish pack</button>
                </form>
            </ChatDialog>}
        </AppLayout>
    );
}
