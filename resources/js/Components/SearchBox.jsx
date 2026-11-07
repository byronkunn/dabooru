import { Link, router } from '@inertiajs/react';
import { Icon } from '@/Components/Icons';
import { useEffect, useRef, useState } from 'react';

/**
 * Header search. Used on every page (desktop box, mobile icon).
 *
 * - Types ahead against /search/suggest (debounced), keyboard navigable.
 * - Enter or "Search for …" opens the full results page.
 * - The keyboard shortcut "/" focuses it from anywhere.
 */
export default function SearchBox({ initial = '', autoFocus = false, className = '' }) {
    const [term, setTerm] = useState(initial);
    const [open, setOpen] = useState(false);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(-1);
    const boxRef = useRef(null);
    const inputRef = useRef(null);
    const abortRef = useRef(null);

    const trimmed = term.trim();

    // '/' focuses the box from anywhere (unless the user is already typing)
    useEffect(() => {
        const onKey = (e) => {
            const tag = document.activeElement?.tagName;
            const typing = tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement?.isContentEditable;
            if (e.key === '/' && !typing) {
                e.preventDefault();
                inputRef.current?.focus();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // close on outside click
    useEffect(() => {
        const onClick = (e) => {
            if (boxRef.current && !boxRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    // debounced live suggestions
    useEffect(() => {
        if (trimmed.length < 2) {
            setData(null);
            setLoading(false);
            return;
        }

        setLoading(true);
        const timer = setTimeout(() => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            fetch(`${route('search.suggest')}?q=${encodeURIComponent(trimmed)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((r) => (r.ok ? r.json() : Promise.reject(new Error('search failed'))))
                .then((json) => {
                    setData(json);
                    setActive(-1);
                })
                .catch((err) => {
                    if (err.name !== 'AbortError') setData({ people: [], works: [], comics: [] });
                })
                .finally(() => setLoading(false));
        }, 220);

        return () => clearTimeout(timer);
    }, [trimmed]);

    const rows = data
        ? [
              ...data.people.map((p) => ({ ...p, group: 'People' })),
              ...data.comics.map((p) => ({ ...p, group: 'Comics' })),
              ...data.works.map((p) => ({ ...p, group: 'Works' })),
          ]
        : [];

    const go = (url) => {
        setOpen(false);
        router.visit(url);
    };

    const submit = (e) => {
        e?.preventDefault();
        if (active >= 0 && rows[active]) return go(rows[active].url);
        if (trimmed.length === 0) return;
        setOpen(false);
        router.get(route('search'), { q: trimmed }, { preserveScroll: false });
    };

    const onKeyDown = (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setOpen(true);
            setActive((i) => Math.min(rows.length - 1, i + 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((i) => Math.max(-1, i - 1));
        } else if (e.key === 'Escape') {
            setOpen(false);
            inputRef.current?.blur();
        }
    };

    const showPanel = open && trimmed.length >= 1;

    return (
        <div ref={boxRef} className={`relative ${className}`}>
            <form onSubmit={submit} role="search" className="flex">
                <div className="flex w-full items-center gap-2 rounded-full border border-ink-line bg-ink-raised px-3.5 text-ink-muted focus-within:border-[#5b4b6b]">
                    <Icon name="search" className="h-4 w-4 shrink-0" />
                    <input
                        ref={inputRef}
                        type="text"
                        name="q"
                        value={term}
                        autoFocus={autoFocus}
                        placeholder="Search works, artists, comics"
                        aria-label="Search"
                        aria-expanded={showPanel}
                        aria-controls="search-suggest"
                        autoComplete="off"
                        onChange={(e) => {
                            setTerm(e.target.value);
                            setOpen(true);
                        }}
                        onFocus={() => setOpen(true)}
                        onKeyDown={onKeyDown}
                        className="h-10 w-full min-w-0 border-0 bg-transparent p-0 text-sm text-ink-text placeholder:text-ink-faint focus:ring-0 [&::-webkit-search-cancel-button]:hidden"
                    />
                    {term && (
                        <button
                            type="button"
                            onClick={() => {
                                setTerm('');
                                setData(null);
                                inputRef.current?.focus();
                            }}
                            aria-label="Clear search"
                            className="shrink-0 text-ink-faint hover:text-ink-text"
                        >
                            <Icon name="close" className="h-4 w-4" />
                        </button>
                    )}
                </div>
            </form>

            {showPanel && (
                <div
                    id="search-suggest"
                    role="listbox"
                    className="absolute left-0 right-0 top-[calc(100%+8px)] z-50 max-h-[70vh] overflow-y-auto rounded-2xl border border-ink-line bg-ink-surface p-1.5 shadow-2xl"
                >
                    {trimmed.length < 2 ? (
                        <p className="px-3 py-3 text-[12.5px] text-ink-muted">Keep typing — at least 2 characters.</p>
                    ) : loading && rows.length === 0 ? (
                        <p className="px-3 py-3 text-[12.5px] text-ink-muted">Searching…</p>
                    ) : rows.length === 0 ? (
                        <p className="px-3 py-3 text-[12.5px] text-ink-muted">Nothing found for “{trimmed}”.</p>
                    ) : (
                        rows.map((row, i) => (
                            <div key={`${row.group}-${row.url}`}>
                                {(i === 0 || rows[i - 1].group !== row.group) && (
                                    <p className="px-2 pb-1 pt-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-ink-faint">
                                        {row.group}
                                    </p>
                                )}
                                <button
                                    type="button"
                                    role="option"
                                    aria-selected={active === i}
                                    onMouseEnter={() => setActive(i)}
                                    onClick={() => go(row.url)}
                                    className={`flex w-full items-center gap-2.5 rounded-xl px-2 py-2 text-left ${
                                        active === i ? 'bg-ink-raised' : ''
                                    }`}
                                >
                                    {row.avatar ? (
                                        <img src={row.avatar} alt="" className="h-8 w-8 shrink-0 rounded-md bg-ink-raised object-cover" />
                                    ) : (
                                        <span className="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-ink-raised text-ink-faint">
                                            <Icon name="image" className="h-4 w-4" />
                                        </span>
                                    )}
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-[13.5px] font-semibold">{row.label}</span>
                                        <span className="block truncate text-[11.5px] text-ink-muted">{row.hint}</span>
                                    </span>
                                </button>
                            </div>
                        ))
                    )}

                    <button
                        type="button"
                        onClick={submit}
                        className="mt-1 flex w-full items-center gap-2 rounded-xl border-t border-ink-line px-2 py-2.5 text-left text-[13px] font-semibold text-ink-brand hover:bg-ink-raised"
                    >
                        <Icon name="search" className="h-4 w-4" />
                        See all results for “{trimmed}”
                    </button>
                </div>
            )}
        </div>
    );
}
