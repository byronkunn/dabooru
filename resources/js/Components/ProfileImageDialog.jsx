import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/Components/Icons';

export default function ProfileImageDialog({ type, current, onClose }) {
    const form = useForm({ [type]: null });
    const input = useRef(null);
    const [preview, setPreview] = useState(null);
    const file = form.data[type];
    const limit = type === 'avatar' ? 5 : 10;

    useEffect(() => {
        if (!file) {
            setPreview(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    const choose = (event) => {
        const selected = event.target.files[0];
        if (!selected) return;
        form.clearErrors();
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(selected.type) || selected.size > limit * 1024 * 1024) {
            form.setData(type, null);
            form.setError(type, `Choose a JPG, PNG, or WebP image up to ${limit} MB.`);
            return;
        }
        form.setData(type, selected);
    };

    const save = (event) => {
        event.preventDefault();
        form.post(route('settings.profile.image', { type }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onClose={() => !form.processing && onClose()} className="fixed inset-0 z-[80]">
            <div className="fixed inset-0 bg-black/70" aria-hidden="true" />
            <div className="fixed inset-0 flex items-center justify-center p-4">
                <DialogPanel className="max-h-[90dvh] w-full max-w-lg overflow-y-auto rounded-2xl border border-ink-line bg-ink-surface">
                    <form onSubmit={save}>
                        <div className="flex items-center gap-3 border-b border-ink-line p-4">
                            <button type="button" onClick={onClose} disabled={form.processing} aria-label="Close" className="btn-icon-flat shrink-0">
                                <Icon name="close" />
                            </button>
                            <DialogTitle className="text-lg font-bold">Edit {type === 'avatar' ? 'profile photo' : 'banner'}</DialogTitle>
                            <button type="submit" disabled={!file || !!form.errors[type] || form.processing} className="btn-primary ml-auto">
                                {form.processing ? 'Saving...' : 'Save'}
                            </button>
                        </div>
                        <div className="space-y-4 p-4">
                            <div className={`${type === 'avatar' ? 'mx-auto h-40 w-40 rounded-full' : 'aspect-[3/1] w-full rounded-lg'} overflow-hidden bg-ink-raised`}>
                                {(preview || current) && <img src={preview || current} alt={`${type === 'avatar' ? 'Profile photo' : 'Banner'} preview`} className="h-full w-full object-cover" onError={() => {
                                    if (file) form.setError(type, 'This image could not be opened. Please choose another.');
                                }} />}
                            </div>
                            <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" aria-label={`Choose ${type} image`} onChange={choose} disabled={form.processing} className="sr-only" />
                            <button type="button" onClick={() => input.current.click()} disabled={form.processing} className="btn-ghost w-full">
                                <Icon name="camera" /> Choose image
                            </button>
                            <p className="text-center text-xs text-ink-muted">JPG, PNG, or WebP. Up to {limit} MB. {type === 'avatar' ? 'Displayed as a circle.' : 'Wide images work best.'}</p>
                            {form.progress && <p role="status" className="text-center text-sm text-ink-muted">Uploading {form.progress.percentage}%</p>}
                            {form.errors[type] && <p role="alert" className="text-sm text-red-400">{form.errors[type]}</p>}
                        </div>
                    </form>
                </DialogPanel>
            </div>
        </Dialog>
    );
}
