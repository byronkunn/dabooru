import { InboxTabs } from '@/Components/DestinationTabs';
import { Link, router, useForm } from '@inertiajs/react';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { useState } from 'react';
import { Icon } from '@/Components/Icons';

const TABS = [
    ['all', 'All'], ['requests', 'Requests'], ['unread', 'Unread'], ['archived', 'Archive'],
];

export function ChatDialog({ title, onClose, children }) {
    return (
        <Dialog open onClose={onClose} className="relative z-[85]">
            <div className="fixed inset-0 bg-black/70" aria-hidden="true" />
            <div className="fixed inset-0 grid place-items-center overflow-y-auto p-4">
                <DialogPanel className="max-h-[calc(100dvh-32px)] w-full max-w-lg overflow-y-auto rounded-2xl border border-ink-line bg-ink-surface p-5 shadow-2xl">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <DialogTitle className="text-base font-bold">{title}</DialogTitle>
                        <button type="button" className="btn-icon-flat h-9 w-9" onClick={onClose} aria-label="Close">
                            <Icon name="close" />
                        </button>
                    </div>
                    {children}
                </DialogPanel>
            </div>
        </Dialog>
    );
}

export function FormErrors({ errors }) {
    const messages = Object.values(errors ?? {});
    return messages.length > 0 && (
        <ul role="alert" className="space-y-1 text-sm text-ink-expl">
            {messages.map((message, index) => <li key={index}>{message}</li>)}
        </ul>
    );
}

function NewChat({ onClose }) {
    const [username, setUsername] = useState('');

    return (
        <ChatDialog title="New direct message" onClose={onClose}>
            <form onSubmit={(event) => {
                event.preventDefault();
                const handle = username.trim().replace(/^@/, '');
                if (handle) router.get(route('messages.index'), { to: handle });
            }} className="space-y-3">
                <label className="block text-sm">Username
                    <input className="field-input mt-1" value={username} onChange={(event) => setUsername(event.target.value)} required />
                </label>
                <button type="submit" className="btn-primary">Open chat</button>
            </form>
        </ChatDialog>
    );
}

function Privacy({ privacy, onClose }) {
    const form = useForm(privacy);
    return (
        <ChatDialog title="Messaging privacy" onClose={onClose}>
            <form className="space-y-4" onSubmit={(event) => {
                event.preventDefault();
                form.put(route('messages.privacy'), { onSuccess: onClose });
            }}>
                <label className="block text-sm">Who can start a direct message?
                    <select className="field-input mt-1" value={form.data.chat_dm_permission}
                        onChange={(event) => form.setData('chat_dm_permission', event.target.value)}>
                        <option value="anyone">Anyone</option>
                        <option value="following">People I follow</option>
                        <option value="nobody">Nobody</option>
                    </select>
                </label>
                <p className="text-sm text-ink-muted">New DMs from people you don’t follow go to Requests. Existing chats stay in your inbox.</p>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.chat_read_receipts} onChange={(event) => form.setData('chat_read_receipts', event.target.checked)} />
                    Share read receipts when both people enable them
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.chat_allow_forwarding} onChange={(event) => form.setData('chat_allow_forwarding', event.target.checked)} />
                    Let recipients forward messages I send in direct chats
                </label>
                <FormErrors errors={form.errors} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Save privacy</button>
            </form>
        </ChatDialog>
    );
}

export default function Inbox({ conversations, filters = {}, folders = [], privacy, currentId, page = 1, hasMore, requestCount = 0 }) {
    const [newChat, setNewChat] = useState(false);
    const [showPrivacy, setShowPrivacy] = useState(false);
    const [search, setSearch] = useState(filters.q ?? '');
    const tab = filters.tab === 'direct' ? 'all' : (filters.tab ?? 'all');
    const showSavedShortcut = tab === 'all' && !filters.q && !filters.folder;
    const saved = conversations.find((chat) => chat.kind === 'saved');
    const listed = showSavedShortcut ? conversations.filter((chat) => chat.kind !== 'saved') : conversations;
    const inboxUrl = (params) => route('messages.index', { q: filters.q || undefined, tab, folder: filters.folder, ...params });

    return (
        <section aria-label="Inbox" className="min-h-0 min-w-0 overflow-y-auto border-ink-line lg:border-r">
            <InboxTabs />
            <div className="space-y-3 border-b border-ink-line p-3">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-base font-bold">Inbox</h1>
                    <div className="flex flex-wrap justify-end gap-1">
                        {privacy && <button type="button" className="btn-ghost px-2 text-xs" onClick={() => setShowPrivacy(true)}>Privacy</button>}
                        <button type="button" className="btn-primary px-2 text-xs" onClick={() => setNewChat(true)}>New chat</button>
                    </div>
                </div>
                <form className="flex items-center gap-1" onSubmit={(event) => {
                    event.preventDefault();
                    router.get(inboxUrl({ q: search.trim() || undefined }));
                }}>
                    <input type="search" aria-label="Search chats and messages" placeholder="Search chats and messages" value={search} maxLength={100}
                        onChange={(event) => setSearch(event.target.value)} className="field-input min-w-0 flex-1 text-xs" />
                    <button type="submit" className="btn-icon-flat h-10 w-10 shrink-0" aria-label="Search"><Icon name="search" /></button>
                </form>
                <nav aria-label="Inbox filters" className="flex flex-wrap gap-1">
                    {TABS.map(([key, label]) => (
                        <Link href={inboxUrl({ tab: key, folder: undefined })} key={key} aria-current={key === tab ? 'page' : undefined}
                            className={`rounded-full px-2.5 py-1.5 text-xs font-semibold ${key === tab ? 'bg-ink-brand/15 text-ink-brand' : 'text-ink-muted hover:bg-ink-raised'}`}>
                            {label}{key === 'requests' && requestCount > 0 ? ` (${requestCount})` : ''}
                        </Link>
                    ))}
                </nav>
                {folders.length > 0 && (
                    <label className="block text-xs text-ink-muted">Folder
                        <select className="field-input mt-1 text-xs" value={filters.folder ?? ''}
                            onChange={(event) => router.get(inboxUrl({ folder: event.target.value || undefined }))}>
                            <option value="">All folders</option>
                            {folders.map((folder) => <option key={folder}>{folder}</option>)}
                        </select>
                    </label>
                )}
            </div>
            {showSavedShortcut && <Link href={saved ? route('messages.show', saved.id) : route('messages.rooms.store')}
                method={saved ? 'get' : 'post'} as={saved ? 'a' : 'button'} data={saved ? {} : { kind: 'saved' }}
                className="flex min-h-12 w-full items-center gap-2 border-b border-ink-line px-3 text-left text-sm font-semibold hover:bg-ink-raised">
                <Icon name="bookmark" className="h-4 w-4 text-ink-brand" /> Saved Messages
            </Link>}
            {listed.length ? listed.map((chat) => (
                <Link key={chat.id} href={route('messages.show', { conversation: chat.id, q: chat.searchMessages ? filters.q : undefined })}
                    aria-current={chat.id === currentId ? 'page' : undefined}
                    className={`flex min-h-[65px] items-center gap-2.5 border-b border-ink-line px-3 py-2.5 hover:bg-ink-raised ${chat.id === currentId ? 'bg-ink-raised' : ''}`}>
                    {chat.with?.avatar ? <img src={chat.with.avatar} alt="" className="h-9 w-9 shrink-0 rounded-full object-cover" /> :
                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-ink-raised" aria-hidden="true"><Icon name={chat.kind === 'saved' ? 'bookmark' : 'chat'} className="h-4 w-4" /></span>}
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-bold">{chat.title}</span>
                        <span className="block truncate text-xs text-ink-muted">{chat.preview || 'No messages yet'}</span>
                    </span>
                    <span className="flex shrink-0 flex-col items-end gap-1 text-xs text-ink-muted">
                        <span>{chat.last_message_human}</span>
                        {chat.request && <span className="text-ink-brand">Request</span>}
                        {(chat.unread > 0 || chat.markedUnread) && (
                            <span className="rounded-full bg-ink-brand/15 px-1.5 text-ink-brand" aria-label={chat.unread ? `${chat.unread} unread` : 'Marked unread'}>
                                {chat.unread || '●'}
                            </span>
                        )}
                        {chat.pinned && <span>Pinned</span>}
                    </span>
                </Link>
            )) : <p className="p-5 text-sm text-ink-muted">No conversations here.</p>}
            {(page > 1 || hasMore) && <div className="flex gap-2 p-3">
                {page > 1 && <Link className="btn-ghost" href={inboxUrl({ page: page - 1 })}>Previous</Link>}
                {hasMore && <Link className="btn-ghost" href={inboxUrl({ page: page + 1 })}>Next</Link>}
            </div>}
            {newChat && <NewChat onClose={() => setNewChat(false)} />}
            {showPrivacy && <Privacy privacy={privacy} onClose={() => setShowPrivacy(false)} />}
        </section>
    );
}
