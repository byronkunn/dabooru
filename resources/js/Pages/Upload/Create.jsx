import TagField from '@/Components/TagField';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';
import { useRef, useState } from 'react';

const FORMATS = [
    { key: 'image', label: 'Image', icon: '▣', accept: 'image/*', multiple: false },
    { key: 'gif', label: 'GIF', icon: '▤', accept: 'image/gif,video/mp4,video/webm', multiple: false },
    { key: 'video', label: 'Video', icon: '▶', accept: 'video/mp4,video/quicktime,video/webm', multiple: false },
    { key: 'album', label: 'Album', icon: '▦', accept: 'image/*', multiple: true },
    { key: 'comic', label: 'Comic', icon: '▥', accept: 'image/*', multiple: true },
];

const RATINGS = [
    { key: 'sfw', label: 'SFW' },
    { key: 'suggestive', label: 'Suggestive' },
    { key: 'explicit', label: 'Explicit · 18+' },
];

export default function UploadCreate({ limits }) {
    const [type, setType] = useState('image');
    const [previews, setPreviews] = useState([]);
    const inputRef = useRef(null);
    const form = useForm({
        type: 'image',
        title: '',
        caption: '',
        tags: '',
        rating: 'sfw',
        is_original: true,
        artist_name: '',
        source_url: '',
        files: [],
        draft: false,
    });

    const active = FORMATS.find((f) => f.key === type);

    const onPick = (e) => {
        const files = Array.from(e.target.files ?? []);
        form.setData('files', active.multiple ? files : files[0]);
        setPreviews(
            files.map((f) => ({
                name: f.name,
                size: f.size,
                url: f.type.startsWith('image/') ? URL.createObjectURL(f) : null,
            })),
        );
    };

    const chooseFormat = (f) => {
        setType(f.key);
        form.setData({ ...form.data, type: f.key, files: [] });
        setPreviews([]);
        if (inputRef.current) inputRef.current.value = '';
    };

    const submit = (e, draft = false) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, draft }));
form.post(route('upload.store'), { forceFormData: true });
    };

    const goComic = () => {
        window.location.href = route('comics.chapters.create');
    };

    return (
        <AppLayout>
            <Head title="Upload work" />
            <TopBar
                title="Upload work"
                back={route('gallery')}
                actions={
                    <button type="button" onClick={type === 'comic' ? goComic : (event) => submit(event)} disabled={form.processing} className="btn-primary h-10 px-4">
                        {type === 'comic' ? 'Continue' : form.processing ? 'Publishing…' : 'Publish'}
                    </button>
                }
            />

            <form onSubmit={submit} className="mx-auto max-w-[760px] p-3.5 md:p-5">
                <input
                    ref={inputRef}
                    type="file"
                    accept={active.accept}
                    multiple={active.multiple}
                    onChange={onPick}
                    className="sr-only"
                    id="files"
                />
                <label
                    htmlFor="files"
                    className="grid min-h-[200px] cursor-pointer place-items-center rounded-[18px] border-2 border-dashed border-[#4a3c58] bg-white/[0.012] p-6 text-center text-ink-muted hover:border-ink-brand hover:text-ink-text"
                >
                    <div>
                        <Icon name="upload" className="mx-auto mb-2 h-8 w-8" />
                        <p className="text-[15px] font-bold text-ink-text">
                            {previews.length
                                ? `${previews.length} file${previews.length === 1 ? '' : 's'} selected`
                                : `Drop ${active.label.toLowerCase()} files or browse`}
                        </p>
                        <p className="mt-1 text-[12.5px]">
                            up to {limits.image_mb}MB each · video up to {limits.video_mb}MB · comic pages up to {limits.comic_page_mb}MB
                        </p>
                    </div>
                </label>
                {form.errors.files && <p className="field-hint text-ink-expl">{form.errors.files}</p>}

                {previews.length > 0 && (
                    <div className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                        {previews.map((p, i) => (
                            <div key={i} className="overflow-hidden rounded-xl border border-ink-line bg-ink-raised">
                                {p.url ? (
                                    <img src={p.url} alt="" className="aspect-square w-full object-cover" />
                                ) : (
                                    <div className="grid aspect-square place-items-center text-xs text-ink-muted">file</div>
                                )}
                                <p className="truncate px-2 py-1.5 text-[11px] text-ink-muted">{p.name}</p>
                            </div>
                        ))}
                    </div>
                )}

                <h2 className="mb-2.5 mt-5 text-sm font-semibold">Format</h2>
                <div className="flex flex-wrap gap-2">
                    {FORMATS.map((f) => (
                        <button
                            key={f.key}
                            type="button"
                            onClick={() => chooseFormat(f)}
                            aria-pressed={type === f.key}
                            className={`flex min-h-[76px] min-w-[68px] flex-1 flex-col items-center justify-center gap-1.5 rounded-xl border px-2 text-[12.5px] font-semibold sm:min-w-[86px] ${
                                type === f.key ? 'border-ink-brand bg-ink-brand/10 text-ink-brand' : 'border-ink-line bg-ink-surface text-ink-muted'
                            }`}
                        >
                            <span className="text-lg">{f.icon}</span>
                            {f.label}
                        </button>
                    ))}
                </div>

                {type === 'comic' ? (
                    <div className="mt-4 rounded-xl border border-ink-line bg-ink-raised p-4 text-sm">
                        <p className="font-semibold">Comic chapters are published from a series.</p>
                        <p className="mt-1 text-[13px] text-ink-muted">Create a series or pick an existing one, then upload the chapter pages.</p>
                        <Link href={route('comics.chapters.create')} className="btn-primary mt-3">
                            Go to comics
                        </Link>
                    </div>
                ) : (
                    <>
                        <div className="mt-4">
                            <label htmlFor="title" className="field-label">Title</label>
                            <input id="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className="field-input" placeholder="Give it a name" maxLength={160} />
                        </div>
                        <div className="mt-3.5">
                            <label htmlFor="caption" className="field-label">Caption</label>
                            <textarea id="caption" rows={3} value={form.data.caption} onChange={(e) => form.setData('caption', e.target.value)} className="field-input resize-none" placeholder="Say something about this work…" maxLength={1000} />
                            <p className="field-hint">{form.data.caption.length} / 1000</p>
                        </div>

                        <div className="mt-3.5"><TagField form={form} /></div>

                        {/* Artist attribution section */}
                        <div className="mt-5 rounded-2xl border border-ink-line bg-ink-raised/50 p-4">
                            <h2 className="text-sm font-semibold text-ink-text">Artist & Creator</h2>
                            <p className="mt-0.5 text-xs text-ink-muted">
                                Did you create this piece yourself, or are you sharing work by an artist not on the site?
                            </p>

                            <div className="mt-3 flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => form.setData({ ...form.data, is_original: true, artist_name: '', source_url: '' })}
                                    className={`flex-1 rounded-xl border p-3 text-left transition-colors ${
                                        form.data.is_original
                                            ? 'border-ink-brand bg-ink-brand/10 text-ink-text'
                                            : 'border-ink-line bg-ink-surface text-ink-muted hover:text-ink-text'
                                    }`}
                                >
                                    <div className="flex items-center gap-2">
                                        <span className={`h-3 w-3 rounded-full border-2 ${form.data.is_original ? 'border-ink-brand bg-ink-brand' : 'border-ink-muted'}`} />
                                        <span className="text-[13.5px] font-bold">My original work</span>
                                    </div>
                                    <p className="mt-1 text-xs text-ink-muted">You created this artwork.</p>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => form.setData({ ...form.data, is_original: false })}
                                    className={`flex-1 rounded-xl border p-3 text-left transition-colors ${
                                        !form.data.is_original
                                            ? 'border-ink-brand bg-ink-brand/10 text-ink-text'
                                            : 'border-ink-line bg-ink-surface text-ink-muted hover:text-ink-text'
                                    }`}
                                >
                                    <div className="flex items-center gap-2">
                                        <span className={`h-3 w-3 rounded-full border-2 ${!form.data.is_original ? 'border-ink-brand bg-ink-brand' : 'border-ink-muted'}`} />
                                        <span className="text-[13.5px] font-bold">Artist not on site</span>
                                    </div>
                                    <p className="mt-1 text-xs text-ink-muted">Credit the external artist & link their source.</p>
                                </button>
                            </div>

                            {!form.data.is_original && (
                                <div className="mt-4 space-y-3 border-t border-ink-line/60 pt-3.5">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label htmlFor="artist_name" className="field-label">
                                                Artist name / handle <span className="text-ink-expl">*</span>
                                            </label>
                                            <input
                                                id="artist_name"
                                                value={form.data.artist_name}
                                                onChange={(e) => form.setData('artist_name', e.target.value)}
                                                className="field-input"
                                                placeholder="e.g. WLOP, John Staub"
                                                maxLength={120}
                                                required={!form.data.is_original}
                                            />
                                            {form.errors.artist_name && (
                                                <p className="field-hint text-ink-expl">{form.errors.artist_name}</p>
                                            )}
                                        </div>
                                        <div>
                                            <label htmlFor="source_url" className="field-label">
                                                Original source or portfolio link
                                            </label>
                                            <input
                                                id="source_url"
                                                type="url"
                                                value={form.data.source_url}
                                                onChange={(e) => form.setData('source_url', e.target.value)}
                                                className="field-input"
                                                placeholder="https://twitter.com/... or ArtStation"
                                                maxLength={500}
                                            />
                                            {form.errors.source_url && (
                                                <p className="field-hint text-ink-expl">{form.errors.source_url}</p>
                                            )}
                                        </div>
                                    </div>
                                    <p className="text-[12px] text-ink-faint">
                                        The site will reflect that this work was created by an external artist who is not on ModernBooru and link to their source.
                                    </p>
                                </div>
                            )}
                        </div>

                        <h2 className="mb-2.5 mt-5 text-sm font-semibold">Rating</h2>
                        <div className="flex flex-wrap gap-2">
                            {RATINGS.map((r) => (
                                <button
                                    key={r.key}
                                    type="button"
                                    onClick={() => form.setData('rating', r.key)}
                                    aria-pressed={form.data.rating === r.key}
                                    className={`min-h-11 rounded-xl border px-4 text-[13px] font-semibold ${
                                        form.data.rating === r.key ? 'border-ink-brand bg-ink-brand/10 text-ink-brand' : 'border-ink-line bg-ink-surface text-ink-muted'
                                    }`}
                                >
                                    {r.label}
                                </button>
                            ))}
                        </div>
                        <p className="field-hint">Explicit work stays hidden for logged-out visitors and anyone with explicit off.</p>

                        <div className="mt-5 flex flex-wrap justify-end gap-2">
                            <button type="button" disabled={form.processing} onClick={(event) => submit(event, true)} className="btn-ghost">Save private draft</button>
                            <button type="submit" disabled={form.processing} className="btn-primary px-6">
                                {form.processing ? 'Publishing…' : 'Publish'}
                            </button>
                        </div>
                    </>
                )}
            </form>
        </AppLayout>
    );
}
