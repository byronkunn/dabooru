import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';
import SearchBox from '@/Components/SearchBox';
import ReportDialog from '@/Components/ReportDialog';
import { useEffect, useRef, useState } from 'react';

/* ------------------------------------------------------------------ bubbles */
function Attachment({ media, onOpen }) {
    if (!media) return null;

    if (media.kind === 'video') {
        return (
            <video
                src={media.url}
                controls
                playsInline
                preload="metadata"
                poster={media.thumb || undefined}
                className="mt-1 block max-h-[320px] w-full max-w-[280px] rounded-xl bg-black object-contain"
            />
        );
    }

    return (
        <button
            type="button"
            onClick={() => onOpen(media)}
            className="mt-1 block w-fit max-w-full overflow-hidden rounded-xl"
            aria-label="Open image"
        >
            <img
                src={media.thumb || media.url}
                alt=""
                loading="lazy"
                className="block max-h-[320px] w-auto max-w-full bg-ink-raised object-cover"
                style={{ maxWidth: '280px' }}
            />
        </button>
    );
}

function Lightbox({ media, onClose }) {
    useEffect(() => {
        const onKey = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-[90] grid place-items-center bg-black/85 p-4" role="dialog" aria-modal="true" onClick={onClose}>
            <button type="button" onClick={onClose} aria-label="Close" className="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white">
                <Icon name="close" />
            </button>
            <img src={media.url} alt="" className="max-h-full max-w-full rounded-lg object-contain" onClick={(e) => e.stopPropagation()} />
        </div>
    );
}

/* ------------------------------------------------------------------ thread */
export default function MessagesShow({ conversation, conversations = [] }) {
    const { auth } = usePage().props;
    const [reporting, setReporting] = useState(false);
    const [lightbox, setLightbox] = useState(null);
    const [attachment, setAttachment] = useState(null);
    const [switcherOpen, setSwitcherOpen] = useState(false);
    const fileInput = useRef(null);
    const endRef = useRef(null);

    const form = useForm({ conversation_id: conversation.id, body: '', file: null });

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [conversation.messages.length]);

    const canSend = (form.data.body.trim().length > 0 || attachment !== null) && !form.processing;

    const send = (e) => {
        e.preventDefault();
        if (!canSend) return;
        form.transform((data) => ({ ...data, file: attachment }));
        form.post(route('messages.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body');
                setAttachment(null);
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const pickFile = (e) => {
        const file = e.target.files?.[0] ?? null;
        setAttachment(file);
    };

    const otherChats = conversations.filter((c) => c.id !== conversation.id);

    return (
        <AppLayout rightRail={false} footer={false}>
            <Head title={`Chat with ${conversation.with?.name}`} />
            <div className="flex h-[calc(100dvh-70px)] flex-col md:h-screen">
                <header className="flex min-h-[58px] shrink-0 items-center gap-2.5 border-b border-ink-line px-3 py-2.5">
                    {/* back to the conversation list — always visible, not just on mobile */}
                    <Link
                        href={route('messages.index')}
                        aria-label="Back to conversations"
                        title="Back to conversations"
                        className="btn-icon-flat -ml-1 shrink-0"
                    >
                        <Icon name="back" />
                    </Link>

                    <img src={conversation.with?.avatar} alt="" className="h-9 w-9 shrink-0 rounded-full bg-ink-raised object-cover" />
                    <div className="min-w-0">
                        <Link href={route('profile.show', conversation.with?.username)} className="block truncate text-[14.5px] font-bold hover:underline">
                            {conversation.with?.name}
                        </Link>
                        <p className="truncate text-[12.5px] text-ink-muted">@{conversation.with?.username}</p>
                    </div>

                    <div className="ml-auto flex shrink-0 items-center gap-1.5">
                        {/* switcher: jump to another chat without leaving the thread */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setSwitcherOpen((v) => !v)}
                                aria-expanded={switcherOpen}
                                aria-label="Switch conversation"
                                className="btn-ghost h-9 gap-1.5 px-2.5 text-[12.5px]"
                            >
                                <Icon name="chat" className="h-4 w-4" />
                                <span className="hidden sm:inline">Chats</span>
                                <span className="rounded-full bg-ink-raised px-1.5 text-[11px] font-bold">{conversations.length}</span>
                            </button>
                            {switcherOpen && (
                                <>
                                    <div className="fixed inset-0 z-40" onClick={() => setSwitcherOpen(false)} aria-hidden="true" />
                                    <div className="absolute right-0 z-50 mt-2 max-h-[60vh] w-[290px] overflow-y-auto rounded-2xl border border-ink-line bg-ink-surface p-1.5 shadow-2xl">
                                        <Link
                                            href={route('messages.index')}
                                            className="flex items-center gap-2 rounded-xl px-2 py-2 text-[13px] font-semibold text-ink-brand hover:bg-ink-raised"
                                        >
                                            All conversations →
                                        </Link>
                                        <div className="my-1 h-px bg-ink-line" />
                                        {otherChats.length === 0 && <p className="px-2 py-2 text-[12.5px] text-ink-muted">No other chats.</p>}
                                        {otherChats.slice(0, 20).map((c) => (
                                            <Link key={c.id} href={route('messages.show', c.id)} className="flex items-center gap-2.5 rounded-xl px-2 py-2 hover:bg-ink-raised">
                                                <img src={c.with?.avatar} alt="" className="h-8 w-8 shrink-0 rounded-full bg-ink-raised object-cover" />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-[13px] font-semibold">{c.with?.name}</span>
                                                    <span className="block truncate text-[11.5px] text-ink-muted">{c.preview || 'No messages yet'}</span>
                                                </span>
                                            </Link>
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={() => setReporting(true)}
                            className="btn-icon-flat h-9 w-9 text-ink-muted hover:text-ink-text"
                            title="Report user"
                            aria-label="Report user"
                        >
                            <Icon name="flag" className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                if (window.confirm(`Block @${conversation.with.username}? They won't be able to message you or see your posts.`)) {
                                    router.post(route('users.block', conversation.with.username));
                                }
                            }}
                            className="btn-ghost hidden h-8 px-2.5 text-xs sm:block"
                            aria-label="Block user"
                        >
                            Block
                        </button>
                    </div>
                </header>

                <div className="flex-1 space-y-2.5 overflow-y-auto px-3 py-4 md:px-4">
                    {conversation.messages.map((m) => (
                        <div
                            key={m.id}
                            className={`w-fit max-w-[85%] rounded-2xl px-3.5 py-2.5 text-[14.5px] leading-snug md:max-w-[78%] ${
                                m.mine ? 'ml-auto rounded-br-[5px] bg-gradient-to-br from-[#5a3b1f] to-[#4a2436]' : 'rounded-bl-[5px] bg-ink-raised'
                            }`}
                        >
                            {m.body && <p className="break-words whitespace-pre-wrap">{m.body}</p>}
                            <Attachment media={m.media} onOpen={setLightbox} />
                            <span className="mt-1 block text-[10.5px] text-white/45">{m.created_at_human}</span>
                        </div>
                    ))}
                    <div ref={endRef} />
                </div>

                <form onSubmit={send} className="shrink-0 border-t border-ink-line px-3 py-3">
                    {attachment && (
                        <div className="mb-2 flex items-center gap-2.5 rounded-xl border border-ink-line bg-ink-raised px-2.5 py-2">
                            {attachment.type.startsWith('image/') ? (
                                <img src={URL.createObjectURL(attachment)} alt="" className="h-10 w-10 rounded-lg object-cover" />
                            ) : (
                                <span className="grid h-10 w-10 place-items-center rounded-lg bg-ink-surface text-ink-muted">
                                    <Icon name="play" className="h-4 w-4" />
                                </span>
                            )}
                            <span className="min-w-0 flex-1 truncate text-[12.5px] text-ink-muted">{attachment.name}</span>
                            <button
                                type="button"
                                onClick={() => {
                                    setAttachment(null);
                                    if (fileInput.current) fileInput.current.value = '';
                                }}
                                aria-label="Remove attachment"
                                className="btn-icon-flat h-8 w-8"
                            >
                                <Icon name="close" className="h-4 w-4" />
                            </button>
                        </div>
                    )}
                    {form.errors.body && <p className="mb-1 text-[12px] text-ink-expl">{form.errors.body}</p>}
                    {form.errors.file && <p className="mb-1 text-[12px] text-ink-expl">{form.errors.file}</p>}

                    <div className="flex items-center gap-2.5">
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/*,video/mp4,video/webm"
                            onChange={pickFile}
                            className="sr-only"
                            id="dm-file"
                        />
                        <label
                            htmlFor="dm-file"
                            className="btn-icon-flat h-11 w-11 shrink-0 cursor-pointer"
                            title="Attach an image or video"
                            aria-label="Attach an image or video"
                        >
                            <Icon name="plus" className="h-5 w-5" />
                        </label>
                        <input
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                            placeholder="Start a message"
                            aria-label="Message"
                            className="field-input min-w-0 flex-1 rounded-full bg-ink-raised"
                        />
                        <button type="submit" disabled={!canSend} className="btn-primary h-11 w-11 shrink-0 p-0" aria-label="Send">
                            {form.processing ? <span className="h-4 w-4 animate-spin rounded-full border-2 border-black/30 border-t-black" /> : <Icon name="send" className="h-5 w-5" />}
                        </button>
                    </div>
                    <p className="mt-1.5 px-1 text-[11px] text-ink-faint">Images and videos up to 40MB. Photos are re-encoded to strip location data.</p>
                </form>
            </div>

            {lightbox && <Lightbox media={lightbox} onClose={() => setLightbox(null)} />}
            {reporting && conversation.with && <ReportDialog type="user" id={conversation.with.id} onClose={() => setReporting(false)} />}
        </AppLayout>
    );
}
