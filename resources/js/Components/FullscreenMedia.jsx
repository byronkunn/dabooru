import { useEffect, useRef } from 'react';
import { Icon } from '@/Components/Icons';

/**
 * One full-screen media viewer used by both work pages and direct messages.
 *
 * There used to be two hand-rolled copies that had already drifted apart (one
 * paused media and supported swipe, the other did not). Keeping a single
 * component means behaviour like keyboard handling, wrap-around paging and
 * "never autoplay video" is defined once and applies everywhere.
 *
 * Video is deliberately never autoplayed: it opens on its poster frame and
 * waits for the reader to press play.
 */
export default function FullscreenMedia({
    items = [],
    index = 0,
    onNavigate,
    onClose,
    label,
    headerStart = null,
    mediaClassName = '',
}) {
    const mediaRef = useRef(null);
    const touch = useRef(null);
    const total = items.length;
    const current = items[index];

    const go = (delta) => {
        if (total < 2) return;
        // stop whatever is playing so a clip never runs behind the next item
        mediaRef.current?.pause?.();
        onNavigate((index + delta + total) % total);
    };

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') {
                mediaRef.current?.pause?.();
                onClose();
            }
            if (e.key === 'ArrowLeft') { e.preventDefault(); go(-1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); go(1); }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    if (!current) return null;

    const kind = current.kind ?? 'image';

    return (
        <div
            className="fixed inset-0 z-[90] flex flex-col bg-black/95 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-label={label ?? (total > 1 ? `Media ${index + 1} of ${total}` : 'Media')}
            onClick={onClose}
            onTouchStart={(e) => { touch.current = e.touches[0].clientX; }}
            onTouchEnd={(e) => {
                if (touch.current === null) return;
                const dx = e.changedTouches[0].clientX - touch.current;
                if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
                touch.current = null;
            }}
        >
            <header className="flex min-h-[56px] shrink-0 items-center gap-3 border-b border-white/10 px-4 py-2 text-white" onClick={(e) => e.stopPropagation()}>
                {headerStart && <div className="min-w-0 truncate text-sm font-semibold">{headerStart}</div>}
                <span className={`text-sm font-semibold ${headerStart ? 'text-white/60' : ''}`}>
                    {total > 1 ? `${index + 1} of ${total}` : ''}
                </span>
                <button
                    type="button"
                    onClick={onClose}
                    className="btn-icon-flat ml-auto h-9 w-9 text-white hover:bg-white/10"
                    aria-label="Close"
                >
                    <Icon name="close" className="h-5 w-5" />
                </button>
            </header>

            <div className="relative flex flex-1 items-center justify-center overflow-hidden p-4">
                {total > 1 && (
                    <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); go(-1); }}
                        className="btn-icon-flat absolute left-2 top-1/2 z-10 h-11 w-11 -translate-y-1/2 rounded-full bg-black/70 p-2 text-white ring-1 ring-white/20 hover:bg-black/90 md:left-4"
                        aria-label="Previous"
                    >
                        <Icon name="back" className="h-6 w-6" />
                    </button>
                )}

                {kind === 'video' ? (
                    <video
                        ref={mediaRef}
                        src={current.url}
                        poster={current.thumb || undefined}
                        controls
                        playsInline
                        preload="metadata"
                        className={`max-h-[82vh] max-w-[90vw] rounded-lg bg-black object-contain ${mediaClassName}`}
                        onClick={(e) => e.stopPropagation()}
                    />
                ) : kind === 'audio' ? (
                    <div className="w-full max-w-md rounded-xl bg-ink-surface p-4" onClick={(e) => e.stopPropagation()}>
                        <p className="mb-2 truncate text-sm font-semibold text-ink-text">{current.name || 'Audio message'}</p>
                        <audio ref={mediaRef} src={current.url} controls preload="metadata" className="w-full" />
                    </div>
                ) : (
                    <img
                        src={current.url}
                        alt=""
                        onClick={(e) => e.stopPropagation()}
                        className={`max-h-[82vh] max-w-[90vw] select-none object-contain ${mediaClassName}`}
                    />
                )}

                {total > 1 && (
                    <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); go(1); }}
                        className="btn-icon-flat absolute right-2 top-1/2 z-10 h-11 w-11 -translate-y-1/2 rotate-180 rounded-full bg-black/70 p-2 text-white ring-1 ring-white/20 hover:bg-black/90 md:right-4"
                        aria-label="Next"
                    >
                        <Icon name="back" className="h-6 w-6" />
                    </button>
                )}
            </div>
        </div>
    );
}
