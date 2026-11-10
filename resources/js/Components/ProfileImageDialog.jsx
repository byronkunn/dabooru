import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/Components/Icons';

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
const distance = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);

function CropStage({ src, type, zoom = 1, pan = { x: 0, y: 0 }, onPan, onZoom, onSize, onImage, onImageError, interactive = false, className = '' }) {
    const stage = useRef(null);
    const pointers = useRef(new Map());
    const gesture = useRef(null);
    const [size, setSize] = useState({ width: 0, height: 0 });
    const [natural, setNatural] = useState({ width: 0, height: 0 });
    const ratio = type === 'avatar' ? '1 / 1' : '3 / 1';

    useEffect(() => {
        if (!stage.current) return undefined;
        const update = () => {
            const rect = stage.current?.getBoundingClientRect();
            if (!rect) return;
            const next = { width: rect.width, height: rect.height };
            setSize(next);
            onSize?.(next);
        };
        update();
        if (typeof ResizeObserver === 'undefined') {
            window.addEventListener('resize', update);
            return () => window.removeEventListener('resize', update);
        }
        const observer = new ResizeObserver(update);
        observer.observe(stage.current);
        return () => observer.disconnect();
    }, [onSize]);

    const scale = natural.width && size.width
        ? Math.max(size.width / natural.width, size.height / natural.height) * zoom
        : 1;
    const imageWidth = natural.width * scale;
    const imageHeight = natural.height * scale;
    const maxX = Math.max(0, (imageWidth - size.width) / 2);
    const maxY = Math.max(0, (imageHeight - size.height) / 2);
    const translateX = pan.x * maxX;
    const translateY = pan.y * maxY;

    const pointerDown = (event) => {
        if (!interactive) return;
        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        pointers.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
        if (pointers.current.size === 1) {
            gesture.current = { mode: 'drag', start: { x: event.clientX, y: event.clientY }, pan };
        } else {
            const [first, second] = [...pointers.current.values()];
            gesture.current = { mode: 'pinch', distance: distance(first, second), zoom };
        }
    };

    const pointerMove = (event) => {
        if (!interactive || !pointers.current.has(event.pointerId)) return;
        event.preventDefault();
        pointers.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
        if (pointers.current.size > 1 && gesture.current?.mode === 'pinch') {
            const [first, second] = [...pointers.current.values()];
            const startDistance = gesture.current.distance || 1;
            onZoom?.(clamp(gesture.current.zoom * distance(first, second) / startDistance, 1, 3));
        } else if (gesture.current?.mode === 'drag') {
            const dx = event.clientX - gesture.current.start.x;
            const dy = event.clientY - gesture.current.start.y;
            onPan?.({
                x: maxX ? clamp(gesture.current.pan.x + dx / maxX, -1, 1) : 0,
                y: maxY ? clamp(gesture.current.pan.y + dy / maxY, -1, 1) : 0,
            });
        }
    };

    const pointerUp = (event) => {
        pointers.current.delete(event.pointerId);
        if (pointers.current.size === 1) {
            const [point] = [...pointers.current.values()];
            gesture.current = { mode: 'drag', start: point, pan };
        } else {
            gesture.current = null;
        }
    };

    return (
        <div
            ref={stage}
            className={`relative isolate overflow-hidden bg-ink-raised ${type === 'avatar' ? 'rounded-full' : 'rounded-xl'} ${className}`}
            style={{ aspectRatio: ratio, touchAction: interactive ? 'none' : undefined, cursor: interactive ? 'grab' : undefined }}
            onPointerDown={pointerDown}
            onPointerMove={pointerMove}
            onPointerUp={pointerUp}
            onPointerCancel={pointerUp}
            onWheel={interactive ? (event) => { event.preventDefault(); onZoom?.(clamp(zoom - Math.sign(event.deltaY) * 0.08, 1, 3)); } : undefined}
        >
            {src && <img
                src={src}
                alt=""
                draggable="false"
                onLoad={(event) => {
                    const next = { width: event.currentTarget.naturalWidth, height: event.currentTarget.naturalHeight };
                    setNatural(next);
                    onImage?.(next);
                }}
                onError={onImageError}
                className="pointer-events-none absolute left-1/2 top-1/2 max-w-none select-none"
                style={{
                    width: imageWidth || '100%',
                    height: imageHeight || '100%',
                    transform: `translate(-50%, -50%) translate(${translateX}px, ${translateY}px)`,
                }}
            />}
            {interactive && <span className="pointer-events-none absolute inset-x-2 bottom-2 rounded-full bg-black/55 px-2 py-1 text-center text-[11px] font-medium text-white">Drag to reposition</span>}
        </div>
    );
}

function ProfilePreview({ mode, type, src, avatarDisplay, bannerDisplay, zoom, pan }) {
    const width = mode === 'mobile' ? 'max-w-[340px]' : 'max-w-[560px]';
    const avatarSize = mode === 'mobile' ? '-mt-8 h-16 w-16' : '-mt-12 h-24 w-24';
    return (
        <div className={`mx-auto w-full ${width} overflow-hidden rounded-xl border border-ink-line bg-ink-bg`}>
            <div className="relative aspect-[3/1] overflow-hidden bg-gradient-to-br from-[#3a2740] via-[#1d1a24] to-[#3b2a1c]">
                {type === 'banner' ? <CropStage src={src} type="banner" zoom={zoom} pan={pan} className="absolute inset-0 h-full w-full rounded-none" />
                    : bannerDisplay && <img src={bannerDisplay} alt="" className="h-full w-full object-cover" />}
            </div>
            <div className={`relative ${avatarSize} ml-4 overflow-hidden rounded-full border-4 border-ink-bg bg-ink-raised`}>
                {type === 'avatar' ? <CropStage src={src} type="avatar" zoom={zoom} pan={pan} className="h-full w-full rounded-full" />
                    : <img src={avatarDisplay} alt="" className="h-full w-full rounded-full object-cover" />}
            </div>
            <div className="px-4 pb-3 pt-2">
                <div className="h-3 w-28 rounded bg-ink-raised" />
                <div className="mt-2 h-2 w-20 rounded bg-ink-raised/70" />
            </div>
        </div>
    );
}

export default function ProfileImageDialog({ type, current, canRemove = false, avatarDisplay, bannerDisplay, maxMb, onClose }) {
    const form = useForm({ [type]: null, crop: null, remove: false });
    const input = useRef(null);
    const [preview, setPreview] = useState(null);
    const [zoom, setZoom] = useState(1);
    const [pan, setPan] = useState({ x: 0, y: 0 });
    const [stageSize, setStageSize] = useState({ width: 0, height: 0 });
    const [imageSize, setImageSize] = useState({ width: 0, height: 0 });
    const [previewMode, setPreviewMode] = useState('desktop');
    const [loadError, setLoadError] = useState(false);
    const file = form.data[type];
    const source = preview || current;
    const limit = maxMb ?? (type === 'avatar' ? 5 : 10);

    useEffect(() => {
        if (!file) {
            setPreview(null);
            return undefined;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    const reset = () => { setZoom(1); setPan({ x: 0, y: 0 }); };

    const choose = (event) => {
        const selected = event.target.files[0];
        if (!selected) return;
        form.clearErrors();
        setLoadError(false);
        setImageSize({ width: 0, height: 0 });
        reset();
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(selected.type) || selected.size > limit * 1024 * 1024) {
            form.setData(type, null);
            form.setError(type, `Choose a JPG, PNG, or WebP image up to ${limit} MB.`);
            return;
        }
        form.setData(type, selected);
    };

    const crop = () => {
        const baseScale = Math.max(stageSize.width / imageSize.width, stageSize.height / imageSize.height);
        const width = imageSize.width * baseScale * zoom;
        const height = imageSize.height * baseScale * zoom;
        const maxX = Math.max(0, (width - stageSize.width) / 2);
        const maxY = Math.max(0, (height - stageSize.height) / 2);
        const left = (stageSize.width - width) / 2 + pan.x * maxX;
        const top = (stageSize.height - height) / 2 + pan.y * maxY;
        return {
            x: clamp(-left / width, 0, 1),
            y: clamp(-top / height, 0, 1),
            width: clamp(stageSize.width / width, 0.001, 1),
            height: clamp(stageSize.height / height, 0.001, 1),
        };
    };

    const save = (event) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, crop: crop(), remove: false }));
        form.post(route('settings.profile.image', { type }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const remove = () => {
        form.transform(() => ({ remove: true }));
        form.post(route('settings.profile.image', { type }), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const canSave = source && imageSize.width > 0 && stageSize.width > 0 && !loadError && !form.processing;
    const fieldError = form.errors[type] || form.errors.crop || form.errors['crop.x'] || form.errors['crop.y'] || form.errors['crop.width'] || form.errors['crop.height'];

    return (
        <Dialog open onClose={() => !form.processing && onClose()} className="fixed inset-0 z-[80]">
            <div className="fixed inset-0 bg-black/75" aria-hidden="true" />
            <div className="fixed inset-0 flex items-end justify-center sm:items-center sm:p-4">
                <DialogPanel className="flex max-h-[100dvh] w-full max-w-2xl flex-col overflow-hidden border border-ink-line bg-ink-surface sm:max-h-[92dvh] sm:rounded-2xl">
                    <form onSubmit={save} className="flex min-h-0 flex-col">
                        <div className="flex shrink-0 items-center gap-3 border-b border-ink-line p-3 sm:p-4">
                            <button type="button" onClick={onClose} disabled={form.processing} aria-label="Cancel editing" className="btn-icon-flat shrink-0"><Icon name="close" /></button>
                            <DialogTitle className="min-w-0 text-base font-bold sm:text-lg">Edit {type === 'avatar' ? 'profile avatar' : 'profile banner'}</DialogTitle>
                        </div>

                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain p-3 sm:p-5">
                            <CropStage
                                src={source}
                                type={type}
                                zoom={zoom}
                                pan={pan}
                                onPan={setPan}
                                onZoom={setZoom}
                                onSize={setStageSize}
                                onImage={setImageSize}
                                onImageError={() => setLoadError(true)}
                                interactive
                                className={type === 'avatar' ? 'mx-auto w-[min(72vw,300px)]' : 'w-full'}
                            />
                            <div className="flex items-center gap-2">
                                <button type="button" onClick={() => setZoom((value) => clamp(value - 0.1, 1, 3))} aria-label="Zoom out" className="btn-icon-flat h-11 w-11 shrink-0">−</button>
                                <input
                                    type="range"
                                    min="1"
                                    max="3"
                                    step="0.01"
                                    value={zoom}
                                    onChange={(event) => setZoom(Number(event.target.value))}
                                    aria-label="Image zoom"
                                    className="w-full accent-ink-brand"
                                />
                                <button type="button" onClick={() => setZoom((value) => clamp(value + 0.1, 1, 3))} aria-label="Zoom in" className="btn-icon-flat h-11 w-11 shrink-0">+</button>
                            </div>
                            <p className="text-center text-xs text-ink-muted">Zoom {zoom.toFixed(1)}× · Drag with a finger or mouse; pinch to zoom on touchscreens.</p>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <button type="button" onClick={reset} className="btn-ghost min-h-11">Reset position</button>
                                <button type="button" onClick={() => input.current?.click()} className="btn-ghost min-h-11"><Icon name="camera" /> Choose image</button>
                            </div>
                            <input
                                ref={input}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                aria-label={`Choose ${type} image`}
                                onChange={choose}
                                disabled={form.processing}
                                className="sr-only"
                            />

                            <div className="border-t border-ink-line pt-4">
                                <div className="mb-3 flex items-center justify-between gap-2">
                                    <h3 className="text-sm font-semibold">Profile preview</h3>
                                    {type === 'banner' && <div className="flex rounded-full border border-ink-line p-0.5" role="group" aria-label="Preview size">
                                        {['mobile', 'desktop'].map((mode) => <button key={mode} type="button" aria-pressed={previewMode === mode} onClick={() => setPreviewMode(mode)} className={`min-h-9 rounded-full px-3 text-xs font-semibold capitalize ${previewMode === mode ? 'bg-ink-brand text-[#180f18]' : 'text-ink-muted'}`}>{mode}</button>)}
                                    </div>}
                                </div>
                                <ProfilePreview mode={type === 'avatar' ? 'mobile' : previewMode} type={type} src={source} avatarDisplay={avatarDisplay} bannerDisplay={bannerDisplay} zoom={zoom} pan={pan} />
                            </div>

                            <p className="text-center text-xs text-ink-muted">JPG, PNG, or WebP. Avatar up to 5 MB; banner up to 10 MB. The original stays private so you can edit the crop again later.</p>
                            {form.progress && <p role="status" className="text-center text-sm text-ink-muted">Saving image {form.progress.percentage}%</p>}
                            {loadError && <p role="alert" className="text-sm text-red-400">This image could not be opened. Choose another image.</p>}
                            {fieldError && <p role="alert" className="text-sm text-red-400">{fieldError}</p>}
                        </div>
                        <div className="flex shrink-0 items-center justify-between gap-2 border-t border-ink-line bg-ink-surface p-3 sm:p-4" style={{ paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom))' }}>
                            {canRemove ? <button type="button" onClick={remove} disabled={form.processing} className="min-h-11 px-2 text-sm font-semibold text-ink-expl hover:underline">Remove</button>
                                : <button type="button" onClick={onClose} disabled={form.processing} className="btn-ghost min-h-11">Cancel</button>}
                            <button type="submit" disabled={!canSave} className="btn-primary min-h-11 min-w-28 px-4">
                                {form.processing ? 'Saving…' : 'Save'}
                            </button>
                        </div>
                    </form>
                </DialogPanel>
            </div>
        </Dialog>
    );
}
