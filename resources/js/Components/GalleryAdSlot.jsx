export default function GalleryAdSlot({ afterWork }) {
    return (
        <aside
            aria-label="Advertisement"
            data-ad-slot="gallery"
            data-after-work={afterWork}
            className="mb-3.5 block break-inside-avoid overflow-hidden rounded-card border border-ink-line bg-ink-surface"
        >
            <div className="border-b border-ink-line px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-ink-muted">
                Advertisement
            </div>
            <div className="grid aspect-[6/5] place-items-center p-4 text-center">
                <div className="text-sm text-ink-muted">
                    <p className="font-semibold">Ad space</p>
                    <p className="mt-1 text-xs text-ink-faint">Gallery placement</p>
                </div>
            </div>
        </aside>
    );
}
