import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { ChatDialog, FormErrors } from './Inbox';

export default function Stickers({ packs, works, filters = {} }) {
    const [create, setCreate] = useState(false);
    const [search, setSearch] = useState(filters.q ?? '');
    const returnChat = filters.return_chat ?? undefined;
    const back = returnChat ? route('messages.show', returnChat) : route('messages.index');
    const form = useForm({ title: '', post_ids: [], files: [], is_public: false });
    const [previews, setPreviews] = useState([]);
    useEffect(() => {
        const urls = form.data.files.map((file) => ({ url: URL.createObjectURL(file), name: file.name }));
        setPreviews(urls);
        return () => urls.forEach(({ url }) => URL.revokeObjectURL(url));
    }, [form.data.files]);
    return (
        <AppLayout rightRail={false}>
            <Head title="Sticker packs" />
            <TopBar title="Sticker packs" back={back} siteActions={false} exitHref={back} exitLabel={returnChat ? 'Back to chat' : 'Messages'} />
            <div className="mx-auto max-w-4xl space-y-4 p-4">
                <button type="button" className="btn-primary text-xs" onClick={() => setCreate(true)}>New pack</button>
                <form className="flex gap-2" onSubmit={(event) => {
                    event.preventDefault();
                    router.get(route('messages.stickers.index'), { q: search.trim() || undefined, return_chat: returnChat }, { preserveState: true });
                }}>
                    <input type="search" aria-label="Search sticker packs" placeholder="Search packs or creators" className="field-input min-w-0 flex-1"
                        value={search} maxLength={100} onChange={(event) => setSearch(event.target.value)} />
                    <button className="btn-primary" type="submit">Search</button>
                </form>
                <p className="text-sm text-ink-muted">Discover public packs or create a private pack just for you. Uploaded stickers don’t become gallery posts.</p>
                {!packs.length && <p className="py-8 text-center text-sm text-ink-muted">No sticker packs here yet.</p>}
                {packs.map((pack) => <section key={pack.id} className="space-y-3 rounded-xl border border-ink-line p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="min-w-0 flex-1 break-words font-bold">{pack.title}</h2>
                        <Link href={route('profile.show', pack.artist)} className="link-brand text-sm">@{pack.artist}</Link>
                        <span className="rounded-full bg-ink-raised px-2 py-1 text-xs text-ink-muted">{pack.is_public ? 'Public' : 'Private'}</span>
                        {pack.mine && <button type="button" className="btn-ghost text-xs" onClick={() =>
                            router.patch(route('messages.stickers.update', pack.id), { is_public: !pack.is_public }, { preserveScroll: true })
                        }>{pack.is_public ? 'Make private' : 'Publish for everyone'}</button>}
                        {pack.mine && <button type="button" className="btn-ghost text-xs text-ink-expl" onClick={() => {
                            if (window.confirm('Remove this sticker pack?')) router.delete(route('messages.stickers.destroy', {
                                stickerPack: pack.id, return_chat: returnChat,
                            }));
                        }}>Remove pack</button>}
                    </div>
                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-6">
                        {pack.stickers.map((entry) => <img key={`${entry.upload_id ? 'upload' : 'post'}-${entry.id}`} src={entry.thumbnail} alt={entry.title} loading="lazy"
                            className="aspect-square w-full rounded-lg bg-ink-raised object-contain" />)}
                    </div>
                </section>)}
            </div>
            {create && <ChatDialog title="Create a sticker pack" onClose={() => { if (!form.processing) setCreate(false); }}>
                <form className="space-y-3" onSubmit={(event) => {
                    event.preventDefault();
                    form.post(route('messages.stickers.store', { return_chat: returnChat }), { onSuccess: () => { form.reset(); setCreate(false); } });
                }}>
                    <label className="block text-sm">Name
                        <input required className="field-input mt-1" maxLength={80} value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                    </label>
                    <label className="block text-sm">Visibility
                        <select className="field-input mt-1" value={form.data.is_public ? 'public' : 'private'}
                            onChange={(event) => form.setData('is_public', event.target.value === 'public')}>
                            <option value="private">Private — only I can use this pack</option>
                            <option value="public">Public — everyone can find and use it</option>
                        </select>
                    </label>
                    <label className="block text-sm">Upload images or GIFs
                        <input type="file" multiple accept="image/png,image/webp,image/jpeg,image/gif,image/avif" className="mt-1 block w-full min-w-0 text-xs"
                            onChange={(event) => form.setData('files', Array.from(event.target.files ?? []))} />
                    </label>
                    <p className="text-xs text-ink-muted">1–16 stickers per pack, 5 MB each, up to 2048 × 2048 pixels. PNG, WebP, JPG, AVIF or animated GIF. Only upload original SFW artwork. Original files retain animation, transparency and embedded metadata; remove personal metadata before uploading.</p>
                    {previews.length > 0 && <div className="grid max-h-36 grid-cols-4 gap-2 overflow-y-auto">
                        {previews.map(({ url, name }, index) => <div key={url} className="min-w-0 text-center">
                            <img src={url} alt={name} className="h-16 w-full object-contain" />
                            <button type="button" className="text-xs underline" aria-label={`Remove ${name}`} onClick={() =>
                                form.setData('files', form.data.files.filter((_, position) => position !== index))
                            }>Remove</button>
                        </div>)}
                    </div>}
                    <p className="text-sm text-ink-muted">Or choose from your original SFW image posts:</p>
                    <div className="max-h-52 space-y-1 overflow-y-auto">
                        {works.length ? works.map((work) => {
                            const selected = form.data.post_ids.includes(work.id);
                            return <label key={work.id} className="flex items-center gap-2 rounded-lg bg-ink-raised p-2 text-sm">
                                <input type="checkbox" checked={selected} disabled={!selected && form.data.post_ids.length >= 16}
                                    onChange={() => form.setData('post_ids', selected ? form.data.post_ids.filter((id) => id !== work.id) : [...form.data.post_ids, work.id])} />
                                {work.title || `Work #${work.id}`}
                            </label>;
                        }) : <p className="text-sm">No eligible image posts. Upload stickers above instead.</p>}
                    </div>
                    <FormErrors errors={form.errors} />
                    <p className="text-xs text-ink-muted">{form.data.post_ids.length + form.data.files.length} / 16 selected</p>
                    {form.progress && <p role="status" className="text-xs">Uploading {form.progress.percentage}%</p>}
                    <button type="submit" className="btn-primary" disabled={form.processing || !(form.data.post_ids.length + form.data.files.length)
                        || form.data.post_ids.length + form.data.files.length > 16}>{form.processing ? 'Creating…' : 'Create pack'}</button>
                </form>
            </ChatDialog>}
        </AppLayout>
    );
}
