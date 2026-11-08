import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ChatDialog, FormErrors } from './Inbox';

const preference = (id, data) => router.patch(route('messages.preferences', id), data, { preserveScroll: true });

export function ChatSettings({ conversation: chat, onClose }) {
    const [folder, setFolder] = useState(chat.preferences.folder ?? '');
    const [notice, setNotice] = useState('');
    const expiry = useForm({ auto_delete_seconds: chat.options.auto_delete_seconds ?? 0 });

    return (
        <ChatDialog title="Conversation settings" onClose={onClose}>
            <div className="max-h-[65dvh] space-y-4 overflow-y-auto pr-1">
                <div className="flex flex-wrap gap-2">
                    {[
                        ['pinned', 'Pin chat', 'Unpin chat'], ['muted', 'Mute', 'Unmute'],
                        ['archived', 'Archive', 'Unarchive'], ['marked_unread', 'Mark unread', 'Mark unread'],
                    ].map(([key, enable, disable]) => (
                        <button key={key} type="button" className="btn-ghost text-xs"
                            onClick={() => preference(chat.id, { [key]: !chat.preferences[key] })}>
                            {chat.preferences[key] ? disable : enable}
                        </button>
                    ))}
                </div>
                <form className="flex items-end gap-2" onSubmit={(event) => {
                    event.preventDefault();
                    preference(chat.id, { folder: folder.trim() || null });
                    setNotice('Folder updated.');
                }}>
                    <label className="min-w-0 flex-1 text-sm">Folder
                        <input className="field-input mt-1" value={folder} maxLength={40} placeholder="e.g. Commissions"
                            onChange={(event) => setFolder(event.target.value)} />
                    </label>
                    <button type="submit" className="btn-ghost">Save folder</button>
                </form>
                {((chat.kind === 'direct' && !chat.request && chat.canSend) || chat.kind === 'saved') && (
                    <form className="space-y-2 border-t border-ink-line pt-3" onSubmit={(event) => {
                        event.preventDefault();
                        expiry.patch(route('messages.settings', chat.id), { onSuccess: () => setNotice('Auto-delete updated.') });
                    }}>
                        <Expiry value={expiry.data.auto_delete_seconds} onChange={(value) => expiry.setData('auto_delete_seconds', value)} />
                        <FormErrors errors={expiry.errors} />
                        <button type="submit" className="btn-ghost">Save timer</button>
                    </form>
                )}
                <p className="text-sm text-ink-muted" role="status">{notice}</p>
            </div>
        </ChatDialog>
    );
}

function Expiry({ value, onChange }) {
    return <label className="block text-sm">Auto-delete new messages
        <select className="field-input mt-1" value={value} onChange={(event) => onChange(Number(event.target.value))}>
            <option value={0}>Off</option><option value={3600}>After one hour</option>
            <option value={86400}>After one day</option><option value={604800}>After one week</option>
        </select>
        <span className="mt-1 block text-xs text-ink-muted">Applies to messages sent after you save. Reports can retain the reported message while staff review it.</span>
    </label>;
}

export function MessageDialog({ action, message, conversations, conversation, onClose }) {
    const form = useForm({
        body: message.body ?? '',
        conversation_id: conversation.id,
        forward_id: message.id,
    });
    const destinations = conversations.filter((chat) => !chat.request && chat.id !== conversation.id);
    const submit = (event) => {
        event.preventDefault();
        const options = { onSuccess: onClose, preserveScroll: action === 'edit' };
        if (action === 'edit') {
            form.transform(({ body }) => ({ body }));
            form.patch(route('messages.edit', message.id), options);
        } else {
            form.transform(({ conversation_id, forward_id }) => ({ conversation_id, forward_id }));
            form.post(route('messages.store'), options);
        }
    };
    return (
        <ChatDialog title={action === 'edit' ? 'Edit message' : 'Forward message'} onClose={onClose}>
            <form onSubmit={submit} className="space-y-3">
                {action === 'edit' ? <label className="block text-sm">Message
                    <textarea className="field-input mt-1" required maxLength={4000} rows={4} value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} />
                </label> : <label className="block text-sm">Send to
                    <select required className="field-input mt-1" value={form.data.conversation_id === conversation.id ? '' : form.data.conversation_id}
                        onChange={(event) => form.setData('conversation_id', Number(event.target.value))}>
                        <option value="">Choose a conversation</option>
                        {destinations.map((chat) => <option value={chat.id} key={chat.id}>{chat.title}</option>)}
                    </select>
                </label>}
                {action === 'forward' && <p className="text-xs text-ink-muted">Recipients will see the forwarded text and any attached media.</p>}
                <FormErrors errors={form.errors} />
                <button type="submit" className="btn-primary" disabled={form.processing || (action === 'forward' && form.data.conversation_id === conversation.id)}>
                    {action === 'edit' ? 'Save' : 'Forward'}
                </button>
            </form>
        </ChatDialog>
    );
}
