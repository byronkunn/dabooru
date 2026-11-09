import { Head, useForm } from '@inertiajs/react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { AdminErrors, AdminPagination } from '@/Components/AdminUi';

function ShelfForm({ shelf }) {
    const form = useForm({ title: shelf?.title ?? '', description: shelf?.description ?? '', entryText: shelf?.entryText ?? '', published: shelf?.published ?? false });
    const submit = (event) => {
        event.preventDefault();
        if (shelf) {
            form.patch(route('admin.shelves.update', shelf.id), { preserveScroll: true });
        } else {
            form.post(route('admin.shelves.store'), { preserveScroll: true, onSuccess: () => form.reset() });
        }
    };

    return (
        <form className="space-y-3" onSubmit={submit}>
            <label className="block text-sm">Shelf title<input className="field-input mt-1" required maxLength={120} value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} /></label>
            <label className="block text-sm">Description<textarea className="field-input mt-1" rows={2} maxLength={500} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></label>
            <label className="block text-sm">Work, comic, or artist links
                <textarea className="field-input mt-1" required rows={4} maxLength={4000} placeholder={'/p/42\n/comics/series-name\n/u/artist'} value={form.data.entryText} onChange={(event) => form.setData('entryText', event.target.value)} />
                <span className="field-hint">Up to 12 links, one per line, in display order.</span>
            </label>
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.published} onChange={(event) => form.setData('published', event.target.checked)} /> Published status (archived; no public page)</label>
            <AdminErrors errors={form.errors} />
            <button className="btn-primary" disabled={form.processing}>Save shelf</button>
        </form>
    );
}

export default function AdminShelves({ shelves, nextPage, previousPage }) {
    return (
        <AdminLayout>
            <Head title="Curated shelves" />
            <AdminTopBar title="Curated shelves" />
            <div className="space-y-3 p-3.5 md:p-5">
                <details className="panel"><summary className="cursor-pointer font-bold">Create shelf</summary><div className="mt-3"><ShelfForm /></div></details>
                {shelves.length === 0 && (
                    <p className="panel px-4 py-10 text-center text-sm text-ink-muted">
                        No shelves yet. Create one above to group works for the Discover page.
                    </p>
                )}
                {shelves.map((shelf) => <details className="panel" key={shelf.id}>
                    <summary className="cursor-pointer break-words font-bold">{shelf.title} <span className="text-sm font-normal text-ink-muted">· {shelf.published ? 'Published' : 'Draft'}</span></summary>
                    <div className="mt-3"><ShelfForm shelf={shelf} /></div>
                </details>)}
                <AdminPagination {...{ previousPage, nextPage }} />
            </div>
        </AdminLayout>
    );
}
