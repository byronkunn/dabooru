import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';

export default function CollectionDialog({ title, onClose, children }) {
    return <Dialog open onClose={onClose} className="fixed inset-0 z-[90]">
        <div className="fixed inset-0 bg-black/70" aria-hidden="true" />
        <div className="fixed inset-0 flex items-end justify-center sm:items-center sm:p-4">
            <DialogPanel onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    onClose();
                }
            }} className="max-h-[85dvh] w-full overflow-y-auto rounded-t-2xl border border-ink-line bg-ink-surface p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:max-w-lg sm:rounded-2xl sm:p-5">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <DialogTitle className="text-lg font-bold">{title}</DialogTitle>
                    <button type="button" className="btn-ghost" aria-label={`Close ${title}`} onClick={onClose}>Close</button>
                </div>
                {children}
            </DialogPanel>
        </div>
    </Dialog>;
}
