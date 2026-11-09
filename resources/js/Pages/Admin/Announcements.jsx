import { Head, useForm } from '@inertiajs/react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { AdminErrors } from '@/Components/AdminUi';
import { Pagination } from '@/Components/KnowledgeUi';

function AnnouncementForm({ announcement }) {
    const form = useForm({ title: announcement?.title ?? '', body: announcement?.body ?? '', published: announcement?.published ?? false });
    const deletion = useForm({});

    return (
        <div>
            <form className="space-y-3" onSubmit={(event) => {
                event.preventDefault();
                if (announcement) {
                    form.patch(route('admin.announcements.update', announcement.id), { preserveScroll: true });
                } else {
                    form.post(route('admin.announcements.store'), { preserveScroll: true, onSuccess: () => form.reset() });
                }
            }}>
                <label className="block text-sm">Title<input className="field-input mt-1" required maxLength={120} value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} /></label>
                <label className="block text-sm">Message<textarea className="field-input mt-1" required rows={4} maxLength={1000} value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} /></label>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.published} onChange={(event) => form.setData('published', event.target.checked)} /> Publish on site</label>
                <AdminErrors errors={form.errors} />
                <button className="btn-primary" disabled={form.processing || deletion.processing}>Save announcement</button>
            </form>
            {announcement && (
                <button type="button" className="btn-ghost mt-3 text-red-300" disabled={form.processing || deletion.processing} onClick={() => {
                    if (window.confirm('Delete this announcement?')) deletion.delete(route('admin.announcements.destroy', announcement.id), { preserveScroll: true });
                }}>Delete announcement</button>
            )}
            <AdminErrors errors={deletion.errors} />
        </div>
    );
}

export default function Announcements({ announcements }) {
    return (
        <AdminLayout>
            <Head title="Announcements" />
            <AdminTopBar title="Announcements" />
            <div className="space-y-4 p-3.5 md:p-5">
                <p className="text-sm text-ink-muted">The most recently published or updated announcement appears in a dismissible strip across the site. Drafts are private. Editing a message makes it visible again to people who dismissed the old version.</p>
                <section className="panel"><h2 className="mb-3 font-bold">New announcement</h2><AnnouncementForm /></section>
                {announcements.data.length === 0 && (
                    <p className="panel px-4 py-10 text-center text-sm text-ink-muted">
                        No announcements yet. Anything you publish above appears in the strip across the site.
                    </p>
                )}
                {announcements.data.map((announcement) => (
                    <details key={announcement.id} className="panel">
                        <summary className="cursor-pointer break-words font-bold">{announcement.title} <span className="text-sm font-normal text-ink-muted">· {announcement.published ? 'Published' : 'Draft'}</span></summary>
                        <div className="mt-3"><AnnouncementForm announcement={announcement} /></div>
                    </details>
                ))}
                <Pagination data={announcements} />
            </div>
        </AdminLayout>
    );
}
