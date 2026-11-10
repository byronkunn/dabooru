import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { AdminErrors, AdminPagination } from '@/Components/AdminUi';

function MessageCard({ message }) {
    const [removing, setRemoving] = useState(false);
    const form = useForm({ reason: '' });
    const remove = (event) => {
        event.preventDefault();
        if (!window.confirm('Remove this message? Its text and attachment reference will be redacted.')) return;
        form.delete(route('admin.messages.destroy', message.id), { preserveScroll: true, onSuccess: () => setRemoving(false) });
    };
    return <li className="panel space-y-3">
        <div className="flex flex-wrap items-start justify-between gap-2 text-xs text-ink-muted">
            <strong className="text-ink-text">{message.sender ? `@${message.sender.username || message.sender.name}` : 'Unknown sender'} · Message #{message.id}</strong>
            <time dateTime={message.created_at}>{message.created_at ? new Date(message.created_at).toLocaleString() : ''}</time>
        </div>
        {message.removed ? <p className="text-sm italic text-ink-faint">Message removed</p> : message.body && <p className="whitespace-pre-wrap break-words text-sm">{message.body}</p>}
        {message.media && <div className="flex flex-wrap items-center gap-2 rounded-lg bg-ink-raised p-3 text-sm">
            <span>{message.media.kind} · {(message.media.size_bytes / 1024 ** 2).toFixed(2)} MB{message.media.name ? ` · ${message.media.name}` : ''}</span>
            <a className="btn-ghost" href={message.media.url} target="_blank" rel="noreferrer">Reveal attachment</a>
        </div>}
        {message.shared_post_id && <p className="text-xs text-ink-muted">Shared work #{message.shared_post_id}</p>}
        {!message.removed && <div>
            {!removing ? <button type="button" className="btn-ghost text-ink-expl" onClick={() => setRemoving(true)}>Remove message</button> : <form className="space-y-2 rounded-lg border border-ink-line p-3" onSubmit={remove}>
                <label className="block text-sm">Reason for removal
                    <textarea className="field-input mt-1" rows={2} maxLength={500} required value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
                </label>
                <div className="flex flex-wrap gap-2"><button className="btn-primary" disabled={form.processing}>Confirm removal</button><button type="button" className="btn-ghost" onClick={() => { setRemoving(false); form.clearErrors(); }}>Cancel</button></div>
                <AdminErrors errors={form.errors} />
            </form>}
        </div>}
    </li>;
}

export default function AdminMessagesShow({ conversation, messages, filters, total, previousPage, nextPage }) {
    const [query, setQuery] = useState(filters.q);
    const submit = (event) => {
        event.preventDefault();
        router.get(route('admin.messages.show', conversation.id), { q: query }, { preserveState: true, preserveScroll: true });
    };
    return <AdminLayout>
        <Head title={`Messages · Conversation ${conversation.id}`} />
        <AdminTopBar title="Conversation review" subtitle={conversation.participants.map((person) => `@${person.username || person.name}`).join(' · ')} />
        <div className="space-y-4 p-3.5 md:p-5">
            <Link href={route('admin.messages')} className="link-brand text-sm">← Back to message search</Link>
            <form className="panel flex flex-col gap-2 sm:flex-row" onSubmit={submit}>
                <label className="min-w-0 flex-1 text-sm">Search within this conversation
                    <input className="field-input mt-1" type="search" maxLength={100} value={query} onChange={(event) => setQuery(event.target.value)} />
                </label>
                <button className="btn-primary self-end">Search</button>
            </form>
            <p className="text-xs text-ink-muted">{total} messages · newest first. Media is only opened when you select “Reveal attachment”.</p>
            {messages.length ? <ol className="space-y-3">{messages.map((message) => <MessageCard key={message.id} message={message} />)}</ol> : <p className="panel py-10 text-center text-sm text-ink-muted">No messages match this search.</p>}
            <AdminPagination {...{ previousPage, nextPage }} />
        </div>
    </AdminLayout>;
}
