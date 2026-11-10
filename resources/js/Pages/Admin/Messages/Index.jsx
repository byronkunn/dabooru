import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { AdminPagination } from '@/Components/AdminUi';

const formatBytes = (bytes) => bytes >= 1024 ** 3 ? `${(bytes / 1024 ** 3).toFixed(1)} GB` : bytes >= 1024 ** 2 ? `${(bytes / 1024 ** 2).toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`;

export default function AdminMessagesIndex({ conversations, filters, total, previousPage, nextPage }) {
    const [query, setQuery] = useState(filters);
    const submit = (event) => {
        event.preventDefault();
        router.get(route('admin.messages'), query, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title="Messages" />
            <AdminTopBar title="Messages" subtitle={`${total} direct conversations`} />
            <div className="space-y-4 p-3.5 md:p-5">
                <p className="text-sm text-ink-muted">Search and review direct messages. Opening a conversation, searching, revealing media, and removing messages are recorded in the moderation log.</p>
                <form className="panel grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-3" onSubmit={submit}>
                    <label className="text-sm sm:col-span-2 xl:col-span-3">Search message text or participant
                        <input className="field-input mt-1" type="search" maxLength={100} value={query.q} onChange={(event) => setQuery({ ...query, q: event.target.value })} placeholder="Search text or username" />
                    </label>
                    <label className="text-sm">Minimum attachments
                        <input className="field-input mt-1" type="number" min="0" value={query.min_attachments} onChange={(event) => setQuery({ ...query, min_attachments: event.target.value })} />
                    </label>
                    <label className="text-sm">Minimum media size (MB)
                        <input className="field-input mt-1" type="number" min="0" step="0.1" value={query.min_media_mb} onChange={(event) => setQuery({ ...query, min_media_mb: event.target.value })} />
                    </label>
                    <label className="text-sm">Media type
                        <select className="field-input mt-1" value={query.kind} onChange={(event) => setQuery({ ...query, kind: event.target.value })}>
                            <option value="">Any</option><option value="image">Images</option><option value="video">Videos</option><option value="audio">Audio</option><option value="file">Files</option>
                        </select>
                    </label>
                    <label className="text-sm">Sort by
                        <select className="field-input mt-1" value={query.sort} onChange={(event) => setQuery({ ...query, sort: event.target.value })}>
                            <option value="recent">Most recent</option><option value="media">Most media</option>
                        </select>
                    </label>
                    <div className="flex gap-2"><button className="btn-primary">Search</button><Link className="btn-ghost" href={route('admin.messages')}>Clear</Link></div>
                </form>
                {conversations.length ? <ul className="space-y-3">
                    {conversations.map((conversation) => <li key={conversation.id} className="panel flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0">
                            <p className="break-words font-semibold">{conversation.participants.map((person) => `@${person.username || person.name}`).join(' · ') || 'Unknown participants'}</p>
                            <p className="mt-1 text-sm text-ink-muted">{conversation.message_count} messages · {conversation.attachment_count} attachments · {formatBytes(conversation.media_bytes)}</p>
                            <p className="text-xs text-ink-faint">Latest activity: {conversation.latest_message_at ? new Date(conversation.latest_message_at).toLocaleString() : 'Unknown'}</p>
                        </div>
                        <Link className="btn-primary shrink-0" href={route('admin.messages.show', conversation.id)}>Review messages</Link>
                    </li>)}
                </ul> : <p className="panel py-10 text-center text-sm text-ink-muted">No conversations match these filters.</p>}
                <AdminPagination {...{ previousPage, nextPage }} />
            </div>
        </AdminLayout>
    );
}
