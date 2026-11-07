import { Head, Link, usePage } from '@inertiajs/react';
import { Icon } from '@/Components/Icons';
import { useEffect, useMemo, useRef, useState } from 'react';

export default function ComicRead({ comic, chapter, pages, startPage = 1, defaultMode = 'page' }) {
    const { auth } = usePage().props;
    const [mode, setMode] = useState(localStorage.getItem('inkwell.readerMode') || defaultMode);
    const [page, setPage] = useState(startPage);
    const [showChrome, setShowChrome] = useState(true);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const bodyRef = useRef(null);
    const scrollPages = useRef([]);
    const resumePage = useRef(startPage);
    const total = pages.length;

    useEffect(() => {
        const onFsChange = () => setIsFullscreen(Boolean(document.fullscreenElement));
        document.addEventListener('fullscreenchange', onFsChange);
        return () => document.removeEventListener('fullscreenchange', onFsChange);
    }, []);

    const toggleFullscreen = () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {});
        } else {
            document.exitFullscreen().catch(() => {});
        }
    };

    useEffect(() => {
        localStorage.setItem('inkwell.readerMode', mode);
    }, [mode]);

    useEffect(() => {
        if (mode === 'scroll' && resumePage.current > 1) {
            scrollPages.current[resumePage.current - 1]?.scrollIntoView({ block: 'start' });
        }
    }, [mode]);

    const trackScroll = () => {
        if (mode !== 'scroll' || !bodyRef.current) return;
        const viewport = bodyRef.current.getBoundingClientRect();
        const readingLine = viewport.top + viewport.height / 3;
        const index = scrollPages.current.findIndex((image) => image && image.getBoundingClientRect().bottom > readingLine);
        if (index !== -1) setPage(index + 1);
    };

    const go = (n) => setPage(Math.max(1, Math.min(total, n)));

    useEffect(() => {
        const onKey = (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

            if (e.key === 'f' || e.key === 'F') {
                e.preventDefault();
                toggleFullscreen();
                return;
            }

            if (mode !== 'page') return;
            if (e.key === 'ArrowRight' || e.key === ' ') {
                e.preventDefault();
                go(page + 1);
            }
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                go(page - 1);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [mode, page, total]);

    useEffect(() => {
        resumePage.current = page;
    }, [page]);

    // persist reading progress (debounced)
    useEffect(() => {
        if (!auth.user || !total) return;
        const t = setTimeout(() => {
            window.fetch(route('comics.progress', comic.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ chapter_id: chapter.id, page }),
            }).catch(() => {});
        }, 800);
        return () => clearTimeout(t);
    }, [page, auth.user, comic.id, chapter.id, total]);

    const progressPct = useMemo(() => (total ? Math.round((page / total) * 100) : 0), [page, total]);

    return (
        <div className="fixed inset-0 z-[60] flex flex-col bg-[#08070a]">
            <Head title={`${comic.title} — Ch. ${chapter.number}`} />

            <header className={`flex min-h-[56px] shrink-0 items-center gap-2 border-b border-ink-line bg-ink-bg/95 px-2.5 py-2 transition-all duration-200 md:px-3.5 ${showChrome ? 'opacity-100' : 'pointer-events-none -translate-y-full opacity-0'}`}>
                <Link href={route('comics.show', comic.slug)} aria-label="Back to series" className="btn-icon-flat shrink-0">
                    <Icon name="back" />
                </Link>
                <p className="min-w-0 truncate text-sm font-bold">
                    {comic.title} <span className="font-medium text-ink-muted">· Ch. {chapter.number}</span>
                </p>
                <div className="ml-auto flex items-center gap-2">
                    <div className="flex shrink-0 rounded-full bg-ink-raised p-[3px]" role="group" aria-label="Reading mode">
                        <button type="button" onClick={() => setMode('page')} aria-pressed={mode === 'page'} className={`min-h-9 rounded-full px-3 text-[12.5px] font-bold ${mode === 'page' ? 'bg-ink-brand text-[#1c1017]' : 'text-ink-muted'}`}>
                            Page
                        </button>
                        <button type="button" onClick={() => setMode('scroll')} aria-pressed={mode === 'scroll'} className={`min-h-9 rounded-full px-3 text-[12.5px] font-bold ${mode === 'scroll' ? 'bg-ink-brand text-[#1c1017]' : 'text-ink-muted'}`}>
                            Scroll
                        </button>
                    </div>
                    <button
                        type="button"
                        onClick={toggleFullscreen}
                        className="btn-icon-flat hidden h-9 w-9 shrink-0 sm:grid"
                        title={isFullscreen ? 'Exit fullscreen (F)' : 'Fullscreen (F)'}
                        aria-label="Toggle fullscreen"
                    >
                        <Icon name="fullscreen" className="h-4 w-4" />
                    </button>
                </div>
            </header>

            <div ref={bodyRef} onScroll={trackScroll} className="relative min-h-0 flex-1 overflow-auto bg-[#0b0a0d]">
                {mode === 'page' ? (
                    <div className="relative grid h-full place-items-center overflow-auto p-0 md:p-4">
                        <img
                            src={pages[page - 1]?.url}
                            alt={`Page ${page} of ${total}`}
                            className="max-h-full max-w-full object-contain select-none md:rounded md:shadow-[0_20px_60px_-20px_#000]"
                            width={pages[page - 1]?.width}
                            height={pages[page - 1]?.height}
                        />

                        {/* Interactive Click Zones: Left 30% prev, Center 40% toggle chrome, Right 30% next */}
                        <div className="absolute inset-0 flex select-none" aria-hidden="true">
                            <button
                                type="button"
                                tabIndex={-1}
                                onClick={() => go(page - 1)}
                                className="h-full w-[30%] cursor-w-resize opacity-0"
                                aria-label="Previous page zone"
                            />
                            <button
                                type="button"
                                tabIndex={-1}
                                onClick={() => setShowChrome((c) => !c)}
                                className="h-full w-[40%] cursor-pointer opacity-0"
                                aria-label="Toggle reader chrome"
                            />
                            <button
                                type="button"
                                tabIndex={-1}
                                onClick={() => go(page + 1)}
                                className="h-full w-[30%] cursor-e-resize opacity-0"
                                aria-label="Next page zone"
                            />
                        </div>
                    </div>
                ) : (
                    <div className="mx-auto max-w-[880px] space-y-3 p-0 md:p-4" onClick={() => setShowChrome((c) => !c)}>
                        {pages.map((p, index) => (
                            <img key={index} ref={(node) => { scrollPages.current[index] = node; }} src={p.url} alt="" loading="lazy" className="w-full md:rounded-lg" width={p.width} height={p.height} />
                        ))}
                        <div className="px-4 py-8 text-center">
                            <p className="text-[15px] font-bold">End of Chapter {chapter.number}</p>
                            {chapter.next ? (
                                <Link href={chapter.next.url} className="btn-primary mt-3">
                                    Read Ch. {chapter.next.number} →
                                </Link>
                            ) : (
                                <p className="mt-2 text-[13px] text-ink-muted">This is the latest chapter.</p>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {mode === 'page' && (
                <footer className={`flex shrink-0 items-center gap-2.5 border-t border-ink-line bg-ink-bg/95 px-2.5 py-2 transition-all duration-200 md:px-3.5 ${showChrome ? 'opacity-100' : 'pointer-events-none translate-y-full opacity-0'}`}>
                    <button type="button" onClick={() => go(page - 1)} disabled={page === 1} className="btn-soft min-h-10 min-w-11 disabled:opacity-30" aria-label="Previous page">
                        ←
                    </button>
                    <input
                        type="range"
                        min={1}
                        max={total}
                        value={page}
                        onChange={(e) => go(Number(e.target.value))}
                        className="min-w-0 flex-1 accent-ink-brand"
                        aria-label="Page"
                    />
                    <button type="button" onClick={() => go(page + 1)} disabled={page === total} className="btn-soft min-h-10 min-w-11 disabled:opacity-30" aria-label="Next page">
                        →
                    </button>
                    <span className="hidden w-16 text-right text-[12.5px] text-ink-muted sm:inline">
                        {page} / {total}
                    </span>
                </footer>
            )}
        </div>
    );
}
