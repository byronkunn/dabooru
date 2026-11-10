import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { actionLabel, AdminPagination } from '@/Components/AdminUi';

const roleLabel = (state) => [state.administrator && 'Administrator', ...state.roles].filter(Boolean).join(', ') || 'No staff access';

export default function AdminLog({ actions, filters, actionOptions, totalActions, previousPage, nextPage }) {
    const [query, setQuery] = useState({ action: '', from: '', to: '', ...filters });

    return (
        <AdminLayout>
            <Head title="Moderation log" />
            <AdminTopBar title="Moderation log" subtitle={`${totalActions} matching actions`} />
            <div className="space-y-4 p-3.5 md:p-5">
                <form className="panel grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4" onSubmit={(event) => {
                    event.preventDefault();
                    router.get(route('admin.log'), query, { preserveState: true });
                }}>
                    <label className="text-sm">Action
                        <select value={query.action} onChange={(event) => setQuery({ ...query, action: event.target.value })} className="field-input mt-1">
                            <option value="">All actions</option>
                            {actionOptions.map((action) => <option key={action} value={action}>{actionLabel(action)}</option>)}
                        </select>
                    </label>
                    {['from', 'to'].map((field) => <label className="min-w-0 text-sm capitalize" key={field}>{field}
                        <input type="date" className="field-input mt-1 min-w-0" value={query[field]} onChange={(event) => setQuery({ ...query, [field]: event.target.value })} />
                    </label>)}
                    <button className="btn-ghost">Apply filters</button>
                </form>
                {actions.length ? <ol className="space-y-2">
                    {actions.map((action) => (
                        <li key={action.id} className="panel space-y-1 break-words text-sm">
                            <p className="flex flex-wrap gap-x-2"><strong className="capitalize">{actionLabel(action.action)}</strong><span className="text-ink-muted">{action.target_type} {action.target_id ? `#${action.target_id}` : ''}</span></p>
                            <p className="text-xs text-ink-faint">{action.moderator ? `@${action.moderator}` : 'System'} · <time dateTime={action.when}>{new Date(action.when).toLocaleString()}</time></p>
                            {action.reason && <p>{action.reason}</p>}
                            {action.role_change && <p className="text-ink-muted">{roleLabel(action.role_change.before)} → {roleLabel(action.role_change.after)}</p>}
                            {action.message_context && <p className="text-xs text-ink-muted">{action.message_context.conversation_id ? `Conversation #${action.message_context.conversation_id} · ` : ''}{action.message_context.participants?.length ? `Participants: ${action.message_context.participants.map((name) => `@${name}`).join(', ')} · ` : ''}{action.message_context.messages_shown != null ? `${action.message_context.messages_shown} messages shown · ` : ''}{action.message_context.attachment_count != null ? `${action.message_context.attachment_count} attachments · ` : ''}{action.message_context.media_bytes != null ? `${(action.message_context.media_bytes / 1024 ** 2).toFixed(2)} MB · ` : ''}{action.message_context.had_attachment ? 'Included attachment' : ''}{action.message_context.query_used ? ' · Search used' : ''}</p>}
                        </li>
                    ))}
                </ol> : <p className="py-10 text-center text-sm text-ink-muted">No actions match these filters.</p>}
                <AdminPagination {...{ previousPage, nextPage }} />
            </div>
        </AdminLayout>
    );
}
