import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/Components/Icons';
import { ChatDialog, FormErrors } from './Inbox';
import axios from 'axios';

const AUDIO_FORMATS = [
    ['audio/mp4', 'm4a'], ['audio/webm;codecs=opus', 'webm'], ['audio/ogg;codecs=opus', 'ogg'],
];
const VIDEO_FORMATS = [['video/webm;codecs=vp8,opus', 'webm'], ['video/mp4', 'mp4']];

export default function Composer({ conversation, reply, onClearReply, quickReplies = [] }) {
    const [attachment, setAttachment] = useState(null);
    const [tool, setTool] = useState(null);
    const [sendAt, setSendAt] = useState('');
    const [silent, setSilent] = useState(false);
    const [spoiler, setSpoiler] = useState(false);
    const [original, setOriginal] = useState(false);
    const [post, setPost] = useState('');
    const [interactive, setInteractive] = useState(null);
    const [sticker, setSticker] = useState(null);
    const [recording, setRecording] = useState(null);
    const [camera, setCamera] = useState(null);
    const preview = useRef(null);
    const [recordError, setRecordError] = useState('');
    const fileInput = useRef(null);
    const recorder = useRef(null);
    const mounted = useRef(false);
    const requestedRecording = useRef(false);
    const lastTyping = useRef(0);
    const form = useForm({ body: '' });
    const textOnly = conversation.request?.textOnly;
    const draft = conversation.request?.status === 'draft';

    useEffect(() => {
        mounted.current = true;
        return () => {
            mounted.current = false;
            if (recorder.current?.state === 'recording') {
                recorder.current.stop();
            }
        };
    }, []);

    useEffect(() => {
        if (preview.current) preview.current.srcObject = camera;
    }, [camera]);

    const clearAttachment = () => {
        setAttachment(null);
        setOriginal(false);
        if (fileInput.current) fileInput.current.value = '';
    };

    const send = (event) => {
        event.preventDefault();
        if (form.processing || recording || (!form.data.body.trim() && !attachment && !post && !interactive && !sticker)) return;
        const postId = post.match(/(?:\/posts\/)?(\d+)\/?(?:[?#].*)?$/)?.[1];
        if (post && !postId) {
            form.setError('shared_post_id', 'Enter a work ID or an Inkwell work link.');
            return;
        }
        form.transform((data) => ({
            ...data,
            conversation_id: conversation.id,
            reply_to_id: reply?.id ?? null,
            file: attachment,
            silent,
            spoiler: sendAt ? false : spoiler,
            original,
            shared_post_id: postId ?? null,
            interactive,
            sticker_pack_id: sticker?.pack ?? null,
            sticker_post_id: sticker && !sticker.upload_id ? sticker.id : null,
            sticker_upload_id: sticker?.upload_id ?? null,
            send_at: sendAt ? new Date(sendAt).toISOString() : null,
        }));
        form.post(route('messages.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                lastTyping.current = 0;
                clearAttachment();
                setSendAt('');
                setSilent(false);
                setSpoiler(false);
                setPost('');
                setInteractive(null);
                setSticker(null);
                onClearReply();
            },
        });
    };

    const setBody = (body) => {
        form.setData('body', body);
        if (body && Date.now() - lastTyping.current > 5000 && !draft && conversation.kind === 'direct') {
            lastTyping.current = Date.now();
            axios.post(route('messages.typing', conversation.id)).catch(() => {});
        }
    };

    const record = async (video) => {
        if (recorder.current?.state === 'recording') {
            recorder.current.stop();
            return;
        }
        if (requestedRecording.current) return;
        requestedRecording.current = true;
        setRecordError('');
        let stream;
        try {
            const formats = video ? VIDEO_FORMATS : AUDIO_FORMATS;
            const [mimeType, extension] = formats.find(([mime]) => window.MediaRecorder?.isTypeSupported(mime)) ?? [];
            if (!mimeType || !navigator.mediaDevices?.getUserMedia) throw new Error('Recording requires a supported browser on a secure connection.');
            stream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: video ? { width: { ideal: 480 }, height: { ideal: 480 }, facingMode: 'user' } : false,
            });
            if (!mounted.current) {
                stream.getTracks().forEach((track) => track.stop());
                return;
            }
            const chunks = [];
            const capture = new MediaRecorder(stream, { mimeType });
            recorder.current = capture;
            const timer = window.setTimeout(() => { if (capture.state === 'recording') capture.stop(); }, video ? 60000 : 120000);
            capture.ondataavailable = ({ data }) => { if (data.size) chunks.push(data); };
            capture.onstop = () => {
                window.clearTimeout(timer);
                stream.getTracks().forEach((track) => track.stop());
                if (mounted.current) {
                    setRecording(null);
                    setCamera(null);
                    if (chunks.length) {
                        setAttachment(new File(chunks, `${video ? 'video' : 'voice'}-note.${extension}`, { type: mimeType }));
                        setSticker(null);
                        setSendAt('');
                    }
                }
            };
            capture.start();
            setRecording(video ? 'video' : 'audio');
            if (video) setCamera(stream);
        } catch (error) {
            stream?.getTracks().forEach((track) => track.stop());
            setRecordError(error.name === 'NotAllowedError' ? video ? 'Allow camera and microphone access to record a video note.' : 'Allow microphone access to record a voice note.' : error.message);
        } finally {
            requestedRecording.current = false;
        }
    };

    return (
        <>
            <form onSubmit={send} className="shrink-0 space-y-2 border-t border-ink-line p-3">
                {reply && <div className="flex items-center gap-2 rounded-lg bg-ink-raised px-3 py-2 text-xs">
                    <span className="min-w-0 flex-1 truncate">Replying to {reply.sender} · {reply.body || 'Attachment'}</span>
                    <button type="button" className="btn-icon-flat h-8 w-8" onClick={onClearReply} aria-label="Cancel reply"><Icon name="close" /></button>
                </div>}
                {attachment && <div className="flex items-center gap-2 rounded-lg bg-ink-raised px-3 py-2 text-xs">
                    <span className="min-w-0 flex-1 truncate">{attachment.name}</span>
                    <button type="button" className="btn-icon-flat h-8 w-8" onClick={clearAttachment} aria-label="Remove attachment"><Icon name="close" /></button>
                </div>}
                {interactive && <p className="break-words text-xs">Attached {interactive.type} · {interactive.title}
                    <button type="button" className="ml-2 underline" onClick={() => setInteractive(null)}>Remove</button>
                </p>}
                {sticker && <p className="break-words text-xs">Sticker · {sticker.title}
                    <img src={sticker.thumbnail} alt="" className="inline-block h-16 w-16 object-contain" />
                    <button type="button" className="ml-2 underline" onClick={() => setSticker(null)}>Remove</button>
                </p>}
                {post && <p className="break-all text-xs">Shared work · {post}
                    <button type="button" className="ml-2 underline" onClick={() => setPost('')}>Remove</button>
                </p>}
                {sendAt && <p className="text-xs">Scheduled for {new Date(sendAt).toLocaleString()}
                    <button type="button" className="ml-2 underline" onClick={() => setSendAt('')}>Clear</button>
                </p>}
                <FormErrors errors={form.errors} />
                {camera && <video ref={preview} autoPlay playsInline muted aria-label="Video note preview" className="h-24 w-24 rounded-xl bg-black object-cover" />}
                {recordError && <p role="alert" className="text-sm text-ink-expl">{recordError}</p>}
                {textOnly && <p className="text-xs text-ink-muted">Send one text-only introduction. More messages, stickers and attachments unlock when they accept.</p>}
                <div className="flex items-end gap-2">
                    {!textOnly && <>
                        <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,audio/*,application/pdf,application/zip"
                            onChange={(event) => { setAttachment(event.target.files?.[0] ?? null); setSticker(null); setSendAt(''); }} hidden />
                        <Menu>
                            <MenuButton disabled={form.processing || Boolean(recording)} className="btn-icon-flat h-11 w-11 shrink-0" aria-label="Attachment menu">
                                <Icon name="attachment" />
                            </MenuButton>
                            <MenuItems anchor={{ to: 'top start', gap: 8, padding: 8 }} className="z-50 w-56 max-w-[calc(100vw-16px)] rounded-xl border border-ink-line bg-ink-surface p-1.5 shadow-xl focus:outline-none">
                                <ToolItem onClick={() => fileInput.current.click()}>Photo, video or file</ToolItem>
                                <ToolItem onClick={() => record(false)}>Record voice note</ToolItem>
                                <ToolItem onClick={() => record(true)}>Record video note</ToolItem>
                                <ToolItem onClick={() => setTool('work')}>Share work</ToolItem>
                                <ToolItem onClick={() => setTool('interactive')}>Poll or checklist</ToolItem>
                                <ToolItem onClick={() => setTool('quick')}>Quick replies</ToolItem>
                            </MenuItems>
                        </Menu>
                        <button type="button" className="btn-icon-flat h-11 w-9 shrink-0" disabled={form.processing || Boolean(recording)}
                            onClick={() => setTool('sticker')} aria-label="Stickers">
                            <Icon name="sticker" />
                        </button>
                    </>}
                    <textarea aria-label="Message" rows={1} maxLength={4000} placeholder={textOnly ? 'Write your message request' : 'Write a message'}
                        className="field-input min-h-[44px] min-w-0 flex-1 resize-y text-sm placeholder:truncate" value={form.data.body}
                        onChange={(event) => setBody(event.target.value)} />
                    <button type="submit" aria-label="Send" className="btn-primary h-11 w-11 shrink-0 p-0"
                        disabled={form.processing || recording || (!form.data.body.trim() && !attachment && !interactive && !post && !sticker)}>
                        <Icon name="send" />
                    </button>
                    {!textOnly && <Menu>
                        <MenuButton className="btn-icon-flat h-11 w-7 shrink-0" aria-label="Send options" disabled={form.processing || Boolean(recording)}>
                            <Icon name="more" className="h-4 w-4" />
                        </MenuButton>
                        <MenuItems anchor={{ to: 'top end', gap: 8, padding: 8 }} className="z-50 w-64 max-w-[calc(100vw-16px)] rounded-xl border border-ink-line bg-ink-surface p-1.5 shadow-xl focus:outline-none">
                            <ToolItem onClick={() => setSilent(!silent)}>{silent ? 'Disable silent sending' : 'Send silently'}{silent ? ' (on)' : ''}</ToolItem>
                            {!sendAt && <ToolItem onClick={() => setSpoiler(!spoiler)}>{spoiler ? 'Remove spoiler' : 'Mark as spoiler'}{spoiler ? ' (on)' : ''}</ToolItem>}
                            {attachment?.type.startsWith('image/') && <ToolItem onClick={() => setOriginal(!original)}>
                                {original ? 'Remove original metadata' : 'Keep original file and metadata'}
                            </ToolItem>}
                            {!draft && conversation.request?.status !== 'declined' && !attachment && !post && !interactive && !sticker && !reply &&
                                <ToolItem onClick={() => setTool('schedule')}>Schedule message</ToolItem>}
                        </MenuItems>
                    </Menu>}
                </div>
                {recording && <button type="button" className="btn-ghost text-sm" onClick={() => record(recording === 'video')}>Stop recording {recording === 'video' ? 'video' : 'voice'} note</button>}
                {(silent || spoiler || original) && <p className="text-xs text-ink-brand" role="status">
                    {[silent && 'Silent sending', spoiler && !sendAt && 'Spoiler', original && 'Original metadata included'].filter(Boolean).join(' · ')}
                </p>}
            </form>
            {tool === 'sticker' ? <StickerPicker conversationId={conversation.id} onClose={() => setTool(null)} onPick={(entry) => {
                setSticker(entry); clearAttachment(); setInteractive(null); setPost(''); setSendAt(''); setTool(null);
            }} /> : tool === 'quick' ? <QuickReplies replies={quickReplies} onClose={() => setTool(null)} onUse={(text) => {
                setBody([form.data.body, text].filter(Boolean).join('\n').slice(0, 4000));
                setTool(null);
            }} /> : tool && <ComposerTool tool={tool} post={post} sendAt={sendAt} interactive={interactive} onClose={() => setTool(null)}
                onSave={(value) => {
                    if (tool === 'work') { setPost(value); setSticker(null); setSendAt(''); }
                    if (tool === 'schedule') setSendAt(value);
                    if (tool === 'interactive') { setInteractive(value); setSticker(null); setSendAt(''); }
                    setTool(null);
                }} />}
        </>
    );
}

function ToolItem({ onClick, children }) {
    return <MenuItem><button type="button" onClick={onClick}
        className="block min-h-11 w-full rounded-lg px-3 py-2 text-left text-sm data-[focus]:bg-ink-raised">{children}</button></MenuItem>;
}

function ComposerTool({ tool, post, sendAt, interactive, onClose, onSave }) {
    const [value, setValue] = useState(tool === 'work' ? post : tool === 'schedule' ? sendAt : interactive?.title ?? '');
    const [type, setType] = useState(interactive?.type ?? 'poll');
    const [options, setOptions] = useState(interactive?.options.join('\n') ?? '');
    const lines = options.split('\n').map((line) => line.trim()).filter(Boolean);
    return (
        <ChatDialog title={{ work: 'Share an Inkwell work', schedule: 'Schedule a text message', interactive: 'Poll or checklist' }[tool]} onClose={onClose}>
            <form className="space-y-3" onSubmit={(event) => {
                event.preventDefault();
                onSave(tool === 'interactive' ? { type, title: value.trim(), options: lines } : value.trim());
            }}>
                {tool === 'interactive' && <label className="block text-sm">Type
                    <select className="field-input mt-1" value={type} onChange={(event) => setType(event.target.value)}>
                        <option value="poll">Poll</option><option value="checklist">Checklist</option>
                    </select>
                </label>}
                <label className="block text-sm">
                    {{ work: 'Work link or ID', schedule: 'Send date and time in your time zone', interactive: 'Title' }[tool]}
                    <input required type={tool === 'schedule' ? 'datetime-local' : 'text'} className="field-input mt-1"
                        value={value} onChange={(event) => setValue(event.target.value)} maxLength={tool === 'interactive' ? 140 : undefined} />
                </label>
                {tool === 'interactive' && <label className="block text-sm">Two to ten choices, one per line
                    <textarea required className="field-input mt-1" rows={5} value={options} onChange={(event) => setOptions(event.target.value)} />
                </label>}
                <button type="submit" className="btn-primary" disabled={!value.trim() || (tool === 'interactive' && (lines.length < 2 || lines.length > 10))}>Apply</button>
            </form>
        </ChatDialog>
    );
}

function QuickReplies({ replies, onUse, onClose }) {
    const [entries, setEntries] = useState(replies.join('\n'));
    const form = useForm({ replies });
    return (
        <ChatDialog title="Quick replies" onClose={onClose}>
            {replies.length > 0 && <ul className="mb-4 max-h-44 space-y-1 overflow-y-auto">
                {replies.map((reply) => <li key={reply}><button type="button" onClick={() => onUse(reply)}
                    className="w-full break-words rounded-lg bg-ink-raised p-2 text-left text-sm hover:text-ink-brand">{reply}</button></li>)}
            </ul>}
            <form className="space-y-3" onSubmit={(event) => {
                event.preventDefault();
                form.transform(() => ({ replies: entries.split('\n').map((line) => line.trim()).filter(Boolean) }));
                form.put(route('messages.quick-replies'), { preserveScroll: true, onSuccess: onClose });
            }}>
                <label className="block text-sm">Saved replies, one per line
                    <textarea className="field-input mt-1" rows={4} value={entries} onChange={(event) => setEntries(event.target.value)} />
                </label>
                <FormErrors errors={form.errors} />
                <button className="btn-primary" type="submit" disabled={form.processing}>Save quick replies</button>
            </form>
        </ChatDialog>
    );
}

function StickerPicker({ conversationId, onClose, onPick }) {
    const [packs, setPacks] = useState(null);
    const [failed, setFailed] = useState(false);
    const [attempt, setAttempt] = useState(0);
    const [search, setSearch] = useState('');
    const [selectedPack, setSelectedPack] = useState(null);
    useEffect(() => {
        const controller = new AbortController();
        setFailed(false);
        const timer = setTimeout(() => {
            axios.get(route('messages.stickers.picker'), { params: { q: search.trim() || undefined }, signal: controller.signal })
                .then(({ data }) => setPacks(data)).catch(() => { if (!controller.signal.aborted) setFailed(true); });
        }, 250);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [attempt, search]);
    const available = packs?.filter((pack) => pack.stickers.length);
    const query = search.trim().toLocaleLowerCase();
    const matching = available?.filter((pack) => selectedPack === null || selectedPack === pack.id).map((pack) => ({
        ...pack,
        stickers: pack.stickers.filter((entry) => `${entry.title} ${pack.title} ${pack.artist}`.toLocaleLowerCase().includes(query)),
    })).filter((pack) => pack.stickers.length);
    return (
        <ChatDialog title="Choose a sticker" onClose={onClose}>
            <input type="search" aria-label="Search stickers" placeholder="Search stickers or packs" className="field-input mb-3"
                value={search} onChange={(event) => { setSearch(event.target.value); setSelectedPack(null); }} />
            {available?.length > 0 && <div className="mb-3 flex gap-2 overflow-x-auto pb-2" role="group" aria-label="Sticker packs">
                <button type="button" className="btn-ghost shrink-0 text-xs" aria-pressed={selectedPack === null} onClick={() => setSelectedPack(null)}>All packs</button>
                {available.map((pack) => <button key={pack.id} type="button" aria-pressed={selectedPack === pack.id}
                    className={`shrink-0 rounded-lg border px-3 py-2 text-xs ${selectedPack === pack.id ? 'border-ink-brand text-ink-brand' : 'border-ink-line'}`}
                    onClick={() => setSelectedPack(pack.id)}>{pack.title}</button>)}
            </div>}
            <div className="max-h-[45dvh] space-y-4 overflow-y-auto">
                {failed ? <div role="alert">Couldn’t load sticker packs. <button type="button" className="underline" onClick={() => setAttempt(attempt + 1)}>Retry</button></div> : !packs ? <p>Loading sticker packs…</p>
                    : !available.length && <p className="text-sm text-ink-muted">{query ? 'No matching sticker packs.' : 'No sticker packs are available yet.'}</p>}
                {available?.length > 0 && !matching.length && <p className="text-sm text-ink-muted">No matching stickers.</p>}
                {matching?.map((pack) => <div key={pack.id}>
                    <p className="mb-2 text-sm font-bold">{pack.title} · @{pack.artist}</p>
                    <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                        {pack.stickers.map((entry) => <button key={`${entry.upload_id ? 'upload' : 'post'}-${entry.id}`} type="button" aria-label={`Choose sticker: ${entry.title}`} onClick={() => onPick({ ...entry, pack: pack.id })}
                            className="overflow-hidden rounded-lg bg-ink-raised p-1 hover:ring-1 hover:ring-ink-brand">
                            <img src={entry.thumbnail} alt={entry.title} loading="lazy" className="aspect-square w-full object-contain" />
                        </button>)}
                    </div>
                </div>)}
                <Link href={route('messages.stickers.index', { return_chat: conversationId })} className="link-brand text-sm">Browse or publish packs</Link>
            </div>
        </ChatDialog>
    );
}
