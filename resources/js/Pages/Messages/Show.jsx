import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import usePageVisibility from './usePageVisibility';
import AppLayout, { LegalFooter } from '@/Layouts/AppLayout';
import FullscreenMedia from '@/Components/FullscreenMedia';
import { Icon } from '@/Components/Icons';
import ReportDialog from '@/Components/ReportDialog';
import Inbox from './Inbox';
import Composer from './Composer';
import { ChatSettings, MessageDialog } from './ChatTools';

const EMOJI = ['👍', '❤️', '😂', '🎨', '🔥', '✨'];

function Attachment({ media, onOpen }) {
    if (!media) return null;

    if (media.kind === 'file') {
        return <a className="mt-1 block break-all rounded-lg border border-ink-line px-3 py-2 text-sm text-ink-brand hover:underline"
            href={media.url} download>{media.name || 'Download attachment'}</a>;
    }

    if (media.kind === 'audio') {
        return <audio src={media.url} controls preload="none" className="mt-1 block w-56 max-w-full" />;
    }

    if (media.kind === 'video') {
        return (
            <div className="relative mt-1 w-fit max-w-full">
                <video
                    src={media.url}
                    controls
                    playsInline
                    preload="metadata"
                    poster={media.thumb || undefined}
                    className="block max-h-[320px] w-full max-w-[280px] rounded-xl bg-black object-contain"
                />
                {/* open the clip in the full-screen viewer, where it can page on to the next item */}
                <button
                    type="button"
                    onClick={() => onOpen(media)}
                    aria-label="Open video in the viewer"
                    title="Open in viewer"
                    className="absolute right-1.5 top-1.5 grid h-7 w-7 place-items-center rounded-full bg-black/70 text-white hover:bg-black/90"
                >
                    <Icon name="expand" className="h-3.5 w-3.5" />
                </button>
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={() => onOpen(media)}
            className="mt-1 block w-fit max-w-full overflow-hidden rounded-xl"
            aria-label={media.sticker ? 'Open sticker' : 'Open image'}
        >
            <img
                src={media.thumb || media.url}
                alt=""
                loading="lazy"
                className={`block w-auto max-w-full object-contain ${media.sticker ? 'max-h-40' : 'max-h-[320px] bg-ink-raised'}`}
                style={{ maxWidth: media.sticker ? '160px' : '280px' }}
            />
        </button>
    );
}

/**
 * Collapse a media group into a single bubble.
 *
 * Several photos sent together arrive as separate messages sharing a group id.
 * The first one carries an `album` array; the rest are dropped from the list so
 * they render as one grid instead of a stack of bubbles.
 */
function groupMessages(messages) {
    const albums = new Map();
    for (const message of messages) {
        if (message.media_group) {
            if (!albums.has(message.media_group)) albums.set(message.media_group, []);
            albums.get(message.media_group).push(message);
        }
    }

    const out = [];
    for (const message of messages) {
        if (!message.media_group) { out.push(message); continue; }
        const members = albums.get(message.media_group).slice().sort((a, b) => (a.media_group_position ?? 0) - (b.media_group_position ?? 0));
        if (members[0]?.id !== message.id) continue;
        out.push({ ...message, album: members.map((m) => m.media).filter(Boolean) });
    }

    return out;
}

function MessageBubble({ message, chat, onOpen, onReply, onReport, onAction, messageUrl }) {
    const [revealed, setRevealed] = useState(false);
    const covered = message.spoiler && !revealed;
    const canInteract = !chat.request && (chat.kind !== 'direct' || chat.canSend);
    const request = (method, name, data = {}) => router[method](route(`messages.${name}`, message.id), data, { preserveScroll: true });
    const react = (emoji) => request('put', 'react', { emoji: message.reactions.some((entry) => entry.emoji === emoji && Number(entry.mine)) ? null : emoji });
    const remove = (name) => {
        const label = name === 'hide' ? 'Hide this message from your view?' : 'Remove this message for everyone?';
        if (!window.confirm(label)) return;
        if (name === 'hide') request('post', name);
        else router.delete(route(`messages.${name}`, message.id), { preserveScroll: true });
    };
    return (
        <article id={`chat-message-${message.id}`} data-readable-message={message.removed ? undefined : message.id} className={`relative w-fit min-w-0 max-w-[92%] rounded-2xl px-3 py-2 text-sm leading-snug sm:max-w-[82%] ${message.mine
            ? 'ml-auto rounded-br-sm bg-[#4a2e37]' : 'rounded-bl-sm bg-ink-raised'}`}>
            {message.forwarded_from && <p className="mb-1 break-words text-xs text-ink-brand">Forwarded from {message.forwarded_from}</p>}
            {message.reply && <Link href={messageUrl(message.reply.id)} className="mb-1 block min-w-0 rounded border-l-2 border-ink-brand bg-black/15 px-2 py-1 text-xs">
                <span className="block truncate font-semibold">{message.reply.sender}</span>
                <span className="block truncate text-white/75">{message.reply.label}</span>
            </Link>}
            {covered ? <button type="button" className="rounded-lg border border-ink-line px-3 py-2 text-sm text-ink-brand" onClick={() => setRevealed(true)}>Reveal spoiler</button> : <>
            {message.body && <p className="whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{message.body.split(/(?<![\p{L}\p{M}\p{N}_.@-])(@[\p{L}\p{M}\p{N}_.-]{3,30})(?![\p{L}\p{M}\p{N}_-])/u).map((piece, index) =>
                index % 2 ? <strong className="text-ink-brand" key={index}>{piece}</strong> : piece)}</p>}
            {message.album?.length > 1 ? (
                <div className={`mt-1 grid gap-1 ${message.album.length === 2 ? 'grid-cols-2' : 'grid-cols-3'}`}>
                    {message.album.map((media, index) => (
                        <button key={index} type="button" onClick={() => onOpen(media)} className="overflow-hidden rounded-lg" aria-label={`Open image ${index + 1} of ${message.album.length}`}>
                            <img src={media.thumb || media.url} alt="" loading="lazy" className="h-24 w-full bg-ink-raised object-cover" />
                        </button>
                    ))}
                </div>
            ) : (
                <Attachment media={message.media} onOpen={onOpen} />
            )}
            {message.post?.sticker ? <Link href={`${message.post.pack}&return_chat=${chat.id}`} className="mt-1 block w-fit">
                <img src={message.post.sticker} alt={message.post.title} loading="lazy" className="h-32 max-w-full object-contain" />
                <span className="text-xs text-ink-brand">View sticker pack</span>
            </Link> : message.post && <Link href={message.post.url} className="mt-1 block break-words rounded-lg border border-ink-line px-3 py-2 text-sm text-ink-brand hover:underline">
                <span className="block text-xs text-ink-muted">Dabooru work</span>
                {message.post.title}
            </Link>}
            {message.interactive && message.interactive.type === 'buttons' ? (
                <div className="mt-2 w-64 max-w-full space-y-1.5">
                    {message.interactive.title && <p className="break-words font-semibold">{message.interactive.title}</p>}
                    {message.interactive.answered_by ? (
                        <p className="text-xs">
                            <span className="font-semibold">{message.interactive.answered_by_name || 'They'} chose</span>{' '}
                            <span className="rounded bg-black/25 px-1.5 py-0.5 font-semibold">{message.interactive.answer_label}</span>
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-1.5">
                            {message.interactive.options.map((option, index) => (
                                <button key={option} type="button" disabled={!canInteract}
                                    className="rounded-lg border border-ink-brand/60 bg-ink-brand/10 px-2.5 py-1.5 text-xs font-semibold text-ink-brand disabled:opacity-60"
                                    onClick={() => request('put', 'choice', { index, selected: true })}>
                                    {option}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            ) : message.interactive && (
                <div className="mt-2 w-64 max-w-full space-y-1.5">
                    <p className="break-words font-semibold">{message.interactive.title}</p>
                    <p className="text-xs capitalize text-ink-muted">{message.interactive.type}</p>
                    {message.interactive.options.map((option, index) => {
                        const vote = message.interactive.votes.find((entry) => Number(entry.option_index) === index);
                        const mine = Boolean(Number(message.interactive.type === 'checklist' ? vote?.total : vote?.mine));
                        return <button key={option} type="button" aria-pressed={mine} disabled={!canInteract}
                            className={`flex w-full items-center gap-2 rounded-lg border px-2 py-1.5 text-left text-xs ${mine ? 'border-ink-brand' : 'border-ink-line'}`}
                            onClick={() => request('put', 'choice', { index, selected: !mine })}>
                            <span className="min-w-0 flex-1 break-words">{option}</span>
                            {vote?.total ? <span>{vote.total}</span> : null}
                        </button>;
                    })}
                </div>
            )}
            </>}
            {!message.removed && message.reactions.length > 0 && <div className="mt-1 flex flex-wrap gap-1">
                {message.reactions.map((reaction) => <button type="button" key={reaction.emoji} aria-pressed={Boolean(Number(reaction.mine))} disabled={!canInteract}
                    className="rounded-full bg-black/20 px-2 py-1 text-xs" onClick={() => react(reaction.emoji)}>{reaction.emoji} {reaction.total}</button>)}
            </div>}
            <div className="flex items-center justify-end gap-2 text-[11px] text-white/60">
                {message.pinned && <span>Pinned</span>}
                {message.edited && !message.removed && <span>Edited</span>}
                <span>{message.created_at_human}</span>
                {message.mine && chat.readThrough !== null && chat.readThrough >= message.id && <span aria-label="Read">✓✓</span>}
                {!message.removed && <Menu>
                    <MenuButton className="flex h-8 min-w-8 cursor-pointer items-center justify-center rounded-full text-base hover:bg-black/25"
                        aria-label="Message actions">···</MenuButton>
                    <MenuItems anchor={{ to: message.mine ? 'bottom end' : 'bottom start', gap: 4, padding: 8 }}
                        className="z-[70] w-48 rounded-xl border border-ink-line bg-ink-surface p-1.5 text-xs text-ink-text shadow-xl">
                        {canInteract && <div className="flex flex-wrap justify-around gap-0.5 border-b border-ink-line pb-1">
                            {EMOJI.map((emoji) => <MenuItem as="button" key={emoji} type="button" className="rounded p-1 data-[focus]:bg-ink-raised"
                                aria-label={`React ${emoji}`} onClick={() => react(emoji)}>{emoji}</MenuItem>)}
                        </div>}
                        {[
                            [chat.canSend && !chat.request, 'Reply', () => onReply(message)],
                            [!chat.request && message.canForward, 'Forward', () => onAction('forward', message)],
                            [message.mine && chat.canSend && !chat.request, 'Edit', () => onAction('edit', message)],
                            [canInteract, message.pinned ? 'Unpin message' : 'Pin message', () => request('put', 'pin', { pinned: !message.pinned })],
                            [!message.mine, 'Report message', () => onReport({ type: 'message', id: message.id })],
                            [true, 'Delete for me', () => remove('hide')],
                            [message.mine, 'Delete for everyone', () => remove('destroy')],
                        ].filter(([show]) => show).map(([, label, callback]) => (
                            <MenuItem as="button" type="button" key={label} className="block w-full rounded px-2 py-1.5 text-left data-[focus]:bg-ink-raised"
                                onClick={callback}>{label}</MenuItem>
                        ))}
                    </MenuItems>
                </Menu>}
            </div>
        </article>
    );
}

export default function MessagesShow({ conversation, conversations = [], filters = {}, quickReplies = [] }) {
    return <Thread key={conversation.id} conversation={conversation} conversations={conversations} filters={filters} quickReplies={quickReplies} />;
}

function Thread({ conversation: chat, conversations, filters, quickReplies }) {
    const { auth } = usePage().props;
    const [report, setReport] = useState(null);
    const [lightbox, setLightbox] = useState(null);

    // every image in the thread, in order, so the viewer can page through them
    const gallery = useMemo(() => {
        const out = [];
        for (const message of chat.messages ?? []) {
            if (message.removed) continue;
            const list = message.album?.length ? message.album : (message.media ? [message.media] : []);
            for (const media of list) {
                // video rides along so the pager can move between photos and clips
                if (media && ['image', 'gif', 'video', 'audio'].includes(media.kind ?? 'image')) out.push(media);
            }
        }
        return out;
    }, [chat.messages]);

    const openMedia = (media) => {
        const at = gallery.findIndex((item) => item.url === media.url);
        setLightbox(at === -1 ? null : at);
    };
    const [settings, setSettings] = useState(false);
    const [action, setAction] = useState(null);
    const [reply, setReply] = useState(null);
    const [search, setSearch] = useState(filters.q ?? '');
    const threadRef = useRef(null);
    const nearBottom = useRef(true);
    const previousLast = useRef(null);
    const currentLast = chat.messages[chat.messages.length - 1];
    const browsingHistory = Boolean(filters.before || filters.q);
    const visible = usePageVisibility();
    const updating = visible && !browsingHistory && !settings && !action && !report && !lightbox;
    const syncToken = useRef(chat.syncToken);
    syncToken.current = chat.syncToken;

    useEffect(() => {
        if (!updating) return;
        const controller = new AbortController();
        let timer;
        let refreshed = Date.now();
        const schedule = () => {
            if (!controller.signal.aborted) timer = window.setTimeout(check, 4000);
        };
        const check = async () => {
            if (document.hidden || controller.signal.aborted) return;
            try {
                const { data } = await axios.get(route('messages.sync', chat.id), { signal: controller.signal, timeout: 6000 });
                if (controller.signal.aborted || document.hidden) return;
                if (data.token !== syncToken.current || Date.now() - refreshed >= 30000) {
                    router.reload({
                        only: ['conversation', 'conversations', 'auth'],
                        onSuccess: () => { refreshed = Date.now(); },
                        onFinish: schedule,
                    });
                    return;
                }
            } catch (error) {
                if (error.response?.status === 403 || error.response?.status === 404) {
                    router.visit(route('messages.index'));
                    return;
                }
            }
            schedule();
        };
        schedule();
        return () => { controller.abort(); window.clearTimeout(timer); };
    }, [chat.id, updating]);

    const acknowledged = useRef({ id: chat.readPosition, marked: false });
    const readVersion = useRef(chat.readPosition);
    readVersion.current = chat.readPosition;

    useEffect(() => {
        if (!chat.markedUnread) acknowledged.current.marked = false;
    }, [chat.markedUnread]);

    useEffect(() => {
        const element = threadRef.current;
        if (!visible || browsingHistory || settings || action || report || lightbox || !element) return;
        let pending = 0;
        let inFlight = false;
        let stopped = false;

        const acknowledge = async () => {
            if (stopped || inFlight || document.hidden || !pending) return;
            const id = pending;
            pending = 0;
            const previous = acknowledged.current;
            if (id <= Math.max(previous.id, readVersion.current) && (!chat.markedUnread || previous.marked)) return;
            inFlight = true;
            try {
                const { data } = await axios.post(route('messages.read', chat.id), { message_id: id });
                acknowledged.current = { id: Math.max(id, previous.id), marked: true };
                if (!stopped) router.replaceProp('auth.unread_messages', data.unread);
            } catch {
                // A later visibility change or refreshed message can retry.
            } finally {
                inFlight = false;
                acknowledge();
            }
        };
        if (!window.IntersectionObserver) {
            const scanVisibleMessages = () => {
                const bounds = element.getBoundingClientRect();
                element.querySelectorAll('[data-readable-message]').forEach((message) => {
                    const rect = message.getBoundingClientRect();
                    if (rect.bottom > bounds.top && rect.top < bounds.bottom) {
                        pending = Math.max(pending, Number(message.dataset.readableMessage));
                    }
                });
                acknowledge();
            };
            scanVisibleMessages();
            element.addEventListener('scroll', scanVisibleMessages, { passive: true });
            return () => {
                stopped = true;
                element.removeEventListener('scroll', scanVisibleMessages);
            };
        }
        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting && entry.intersectionRect.height > 0) {
                    pending = Math.max(pending, Number(entry.target.dataset.readableMessage));
                }
            }
            acknowledge();
        }, { root: element, threshold: [0, 0.01] });
        element.querySelectorAll('[data-readable-message]').forEach((message) => observer.observe(message));
        return () => { stopped = true; observer.disconnect(); };
    }, [chat.id, chat.messages, chat.markedUnread, visible, browsingHistory, settings, action, report, lightbox]);

    useEffect(() => {
        const element = threadRef.current;
        if (!element) return;
        const anchor = window.location.hash;
        if (/^#chat-message-\d+$/.test(anchor)) {
            document.getElementById(anchor.slice(1))?.scrollIntoView({ block: 'center' });
        } else if (!browsingHistory && (nearBottom.current || currentLast?.mine)) {
            element.scrollTop = element.scrollHeight;
        } else if (previousLast.current !== currentLast?.id && browsingHistory) {
            element.scrollTop = 0;
        }
        previousLast.current = currentLast?.id;
    }, [browsingHistory, currentLast?.id, currentLast?.mine]);

    const show = (params = {}) => route('messages.show', { conversation: chat.id, q: filters.q, ...params });
    const messageUrl = (id) => show({ before: id + 1, q: undefined }) + `#chat-message-${id}`;
    const sendDecision = (decision) => router.post(route('messages.request', chat.id), { decision }, { preserveScroll: true });
    const resetSearch = () => { setSearch(''); setReply(null); };

    return (
        <AppLayout rightRail={false} footer={false}>
            <Head title={chat.title} />
            <div className="message-window grid min-w-0 grid-cols-1 lg:grid-cols-[minmax(240px,300px)_minmax(0,1fr)]">
                <div className="hidden min-h-0 lg:grid"><Inbox conversations={conversations} currentId={chat.id} /></div>
                <section className="flex min-h-0 min-w-0 flex-col" aria-label={chat.title}>
                    <header className="flex shrink-0 flex-wrap items-center gap-2 border-b border-ink-line p-3">
                        <Link href={route('messages.index')} aria-label="Back to conversations" className="btn-icon-flat h-9 w-9 shrink-0">
                            <Icon name="back" />
                        </Link>
                        {chat.with?.avatar && <img src={chat.with.avatar} alt="" className="h-8 w-8 shrink-0 rounded-full object-cover" />}
                        <div className="min-w-0 flex-1">
                            {chat.with ? <Link href={route('profile.show', chat.with.username)} className="block truncate text-sm font-bold hover:underline">{chat.title}</Link>
                                : <h1 className="truncate text-sm font-bold">{chat.title}</h1>}
                            <p className="truncate text-xs capitalize text-ink-muted">{chat.with ? `@${chat.with.username}` : chat.kind}</p>
                        </div>
                        <button type="button" className="btn-ghost px-2 text-xs" onClick={() => setSettings(true)}>Chat settings</button>
                        {chat.with && <details className="relative">
                            <summary className="btn-icon-flat h-9 w-9 list-none [&::-webkit-details-marker]:hidden" aria-label="Account actions">···</summary>
                            <div className="absolute right-0 z-20 mt-1 w-40 rounded-xl border border-ink-line bg-ink-surface p-1.5 shadow-xl">
                                <button className="btn-ghost w-full text-xs" type="button" onClick={() => setReport({ type: 'user', id: chat.with.id })}>Report account</button>
                                <button className="btn-ghost w-full text-xs" type="button" onClick={() => {
                                    if (window.confirm(`Block @${chat.with.username}?`)) router.post(route('users.block', chat.with.username), {}, {
                                        onSuccess: () => { if (!chat.messages.length) router.visit(route('messages.index')); },
                                    });
                                }}>Block account</button>
                            </div>
                        </details>}
                    </header>
                    {chat.options.auto_delete_seconds > 0 && <p className="shrink-0 border-b border-ink-line px-3 py-1 text-xs text-ink-muted">
                        New messages auto-delete after {{ 3600: 'one hour', 86400: 'one day', 604800: 'one week' }[chat.options.auto_delete_seconds]}.
                    </p>}
                    <form className="flex shrink-0 items-center gap-2 border-b border-ink-line px-3 py-1.5" onSubmit={(event) => {
                        event.preventDefault();
                        router.get(show({ q: search.trim() || undefined, before: undefined }));
                    }}>
                        <input aria-label="Search in conversation" type="search" value={search} maxLength={100} onChange={(event) => setSearch(event.target.value)}
                            className="field-input min-w-0 flex-1 py-1.5 text-xs" placeholder="Search this conversation" />
                        <button type="submit" className="btn-icon-flat h-8 w-8" aria-label="Search"><Icon name="search" className="h-4 w-4" /></button>
                        {browsingHistory && <Link href={show({ before: undefined, q: undefined })} onClick={resetSearch} className="btn-ghost px-2 text-xs">Latest</Link>}
                    </form>
                    {chat.pinned && <Link href={messageUrl(chat.pinned.id)} className="block shrink-0 truncate border-b border-ink-line px-3 py-2 text-xs text-ink-brand">
                        Pinned · {chat.pinned.label}
                    </Link>}
                    <div ref={threadRef} onScroll={({ currentTarget }) => {
                        nearBottom.current = currentTarget.scrollHeight - currentTarget.scrollTop - currentTarget.clientHeight < 80;
                    }} className="min-h-0 flex-1 space-y-3 overflow-y-auto px-3 py-4 sm:px-5" aria-label="Messages">
                        {chat.hasOlder && <div className="text-center"><Link href={show({ before: chat.messages[0]?.id })} className="btn-ghost text-xs">Earlier messages</Link></div>}
                        {!chat.messages.length && <p className="text-center text-sm text-ink-muted">{filters.q ? 'No matching messages.' : 'No messages yet.'}</p>}
                        {groupMessages(chat.messages).map((message) => <MessageBubble key={message.id} message={message} chat={chat}
                            onOpen={openMedia} onReply={setReply} onReport={setReport} onAction={(type, item) => setAction({ type, item })} messageUrl={messageUrl} />)}
                    </div>
                    {chat.typing.length > 0 && <p className="shrink-0 truncate px-3 pb-1 text-xs text-ink-muted" role="status">{chat.typing.join(', ')} typing…</p>}
                    {chat.scheduled.length > 0 && <details className="shrink-0 border-t border-ink-line px-3 py-1.5 text-xs">
                        <summary className="cursor-pointer">Scheduled messages ({chat.scheduled.length})</summary>
                        <ul className="max-h-28 overflow-y-auto">
                            {chat.scheduled.map((entry) => <li key={entry.id} className="flex min-w-0 items-center gap-2 py-1">
                                <span className="min-w-0 flex-1 truncate">{entry.status === 'failed' ? 'Could not send' : new Date(entry.send_at.replace(' ', 'T') + 'Z').toLocaleString()} · {entry.body}</span>
                                <button type="button" className="btn-ghost text-xs" onClick={() => router.delete(route('messages.scheduled.destroy', [chat.id, entry.id]), { preserveScroll: true })}>Remove</button>
                            </li>)}
                        </ul>
                    </details>}
                    {chat.request?.status === 'pending' && !chat.request.mine ? (
                        <div className="shrink-0 border-t border-ink-line p-3">
                            <p className="mb-2 text-sm">Accept this request to reply and exchange attachments.</p>
                            <div className="flex flex-wrap gap-2">
                                {chat.request.canAccept && <button className="btn-primary text-xs" type="button" onClick={() => sendDecision('accepted')}>Accept</button>}
                                <button className="btn-ghost text-xs" type="button" onClick={() => sendDecision('declined')}>Decline</button>
                            </div>
                        </div>
                    ) : chat.canSend ? (
                        <>
                            {chat.request?.status === 'declined' && <p className="px-3 pb-1 text-xs text-ink-muted">Send a message to reopen this conversation.</p>}
                            <Composer conversation={chat} reply={reply} quickReplies={quickReplies} onClearReply={() => setReply(null)} />
                        </>
                    ) : <p className="shrink-0 border-t border-ink-line p-3 text-sm text-ink-muted">
                        {chat.request?.status === 'pending' ? 'Waiting for this message request to be accepted.'
                            : chat.request?.status === 'declined' ? 'This request is closed.'
                                : 'Sending is unavailable for this conversation.'}
                    </p>}
                </section>
            </div>
            <LegalFooter compact className="bg-ink-bg/95 lg:pl-[min(300px,24%)]" />
            {settings && <ChatSettings conversation={chat} onClose={() => setSettings(false)} />}
            {action && <MessageDialog action={action.type} message={action.item} conversations={conversations} conversation={chat} onClose={() => setAction(null)} />}
                        {lightbox !== null && gallery.length > 0 && (
                <FullscreenMedia
                    items={gallery}
                    index={lightbox}
                    onNavigate={setLightbox}
                    onClose={() => setLightbox(null)}
                    label="Message media"
                />
            )}
            {report && auth?.user && <ReportDialog type={report.type} id={report.id} onClose={() => setReport(null)} />}
        </AppLayout>
    );
}
