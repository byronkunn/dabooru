import { Link, router } from '@inertiajs/react';
import { Icon } from '@/Components/Icons';
import { useEffect, useRef, useState } from 'react';

/**
 * Enhanced Header Search Box:
 * - Debounced typeahead with grouped results (Artists, External Artists, Tags, Works, Comics).
 * - Empty state shows Recent Searches (persisted in localStorage) + Trending Tags + Power Syntax guide.
 * - Interactive scope filter tabs: [All] [Works] [Artists] [Comics] [Tags].
 * - Full keyboard navigation (ArrowUp, ArrowDown, Enter, Esc, and global '/' shortcut).
 */
export default function SearchBox({ initial = '', autoFocus = false, className = '' }) {
    const [term, setTerm] = useState(initial);
    const [open, setOpen] = useState(false);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(-1);
    const [scope, setScope] = useState('all');
    const [trending, setTrending] = useState([]);
    const [recentSearches, setRecentSearches] = useState(() => {
        try {
            return JSON.parse(localStorage.getItem('inkwell.recent_searches') || '[]');
        } catch {
            return [];
        }
    });

    const boxRef = useRef(null);
    const inputRef = useRef(null);
    const abortRef = useRef(null);

    const trimmed = term.trim();

    // '/' focuses the box from anywhere (unless typing)
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

    // fetch trending tags once on mount or when focused empty
    useEffect(() => {
        if (!open || trending.length > 0) return;
        fetch(route('search.trending'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((json) => {
                if (json?.tags) setTrending(json.tags);
            })
            .catch(() => {});
    }, [open, trending.length]);

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
                    if (json.trending?.length && !trending.length) {
                        setTrending(json.trending);
                    }
                })
                .catch((err) => {
                    if (err.name !== 'AbortError') {
                        setData({ people: [], external_artists: [], tags: [], works: [], comics: [] });
                    }
                })
                .finally(() => setLoading(false));
        }, 200);

        return () => clearTimeout(timer);
    }, [trimmed]);

    const saveSearch = (query) => {
        const q = query.trim();
        if (!q || q.length < 2) return;
        setRecentSearches((prev) => {
            const next = [q, ...prev.filter((item) => item.toLowerCase() !== q.toLowerCase())].slice(0, 8);
            try {
                localStorage.setItem('inkwell.recent_searches', JSON.stringify(next));
            } catch {}
            return next;
        });
    };

    const removeRecent = (query, e) => {
        e.stopPropagation();
        setRecentSearches((prev) => {
            const next = prev.filter((item) => item !== query);
            try {
                localStorage.setItem('inkwell.recent_searches', JSON.stringify(next));
            } catch {}
            return next;
        });
    };

    const clearRecents = (e) => {
        e.stopPropagation();
        setRecentSearches([]);
        try {
            localStorage.removeItem('inkwell.recent_searches');
        } catch {}
    };

    const allRows = data
        ? [
              ...(data.tags ?? []).map((t) => ({ ...t, group: 'Tags', kind: 'tag' })),
              ...(data.people ?? []).map((p) => ({ ...p, group: 'Artists', kind: 'artist' })),
              ...(data.external_artists ?? []).map((p) => ({ ...p, group: 'Artists', kind: 'external_artist' })),
              ...(data.comics ?? []).map((c) => ({ ...c, group: 'Comics', kind: 'comic' })),
              ...(data.works ?? []).map((w) => ({ ...w, group: 'Works', kind: 'work' })),
          ]
        : [];

    const filteredRows = allRows.filter((r) => {
        if (scope === 'all') return true;
        if (scope === 'tags') return r.kind === 'tag';
        if (scope === 'artists') return r.kind === 'artist' || r.kind === 'external_artist';
        if (scope === 'comics') return r.kind === 'comic';
        if (scope === 'works') return r.kind === 'work';
        return true;
    });

    const go = (url, queryToSave) => {
        if (queryToSave) saveSearch(queryToSave);
        setOpen(false);
        router.visit(url);
    };

    const submit = (e) => {
        e?.preventDefault();
        if (active >= 0 && filteredRows[active]) {
            return go(filteredRows[active].url, filteredRows[active].label);
        }
        if (trimmed.length === 0) return;
        saveSearch(trimmed);
        setOpen(false);
        router.get(route('search'), { q: trimmed }, { preserveScroll: false });
    };

    const onKeyDown = (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setOpen(true);
            setActive((i) => Math.min(filteredRows.length - 1, i + 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((i) => Math.max(-1, i - 1));
        } else if (e.key === 'Escape') {
            setOpen(false);
            inputRef.current?.blur();
        }
    };

    const SCOPES = [
        { key: 'all', label: 'All' },
        { key: 'works', label: 'Works' },
        { key: 'artists', label: 'Artists' },
        { key: 'comics', label: 'Comics' },
        { key: 'tags', label: 'Tags' },
    ];

    return (
        <div ref={boxRef} className={`relative ${className}`}>
            <form onSubmit={submit} role="search" className="flex">
                <div className="flex w-full items-center gap-2 rounded-full border border-ink-line bg-ink-raised px-3.5 text-ink-muted focus-within:border-ink-brand/60 focus-within:ring-1 focus-within:ring-ink-brand/40">
                    <Icon name="search" className="h-4 w-4 shrink-0" />
                    <input
                        ref={inputRef}
                        type="text"
                        name="q"
                        value={term}
                        autoFocus={autoFocus}
                        placeholder="Search works, #tags, by:artist…"
                        aria-label="Search"
                        aria-expanded={open}
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

            {open && (
                <div
                    id="search-suggest"
                    role="listbox"
                    className="absolute left-0 right-0 top-[calc(100%+8px)] z-50 max-h-[75vh] overflow-y-auto rounded-2xl border border-ink-line bg-ink-surface p-2 shadow-2xl backdrop-blur-xl"
                >
                    {/* Zero-query state: Recents, Trending tags, and Syntax shortcuts */}
                    {trimmed.length === 0 ? (
                        <div className="space-y-4 p-2">
                            {recentSearches.length > 0 && (
                                <div>
                                    <div className="mb-2 flex items-center justify-between text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-faint">
                                        <span>Recent searches</span>
                                        <button
                                            type="button"
                                            onClick={clearRecents}
                                            className="text-[11px] font-semibold text-ink-muted hover:text-ink-expl"
                                        >
                                            Clear all
                                        </button>
                                    </div>
                                    <div className="space-y-1">
                                        {recentSearches.map((item) => (
                                            <div
                                                key={item}
                                                onClick={() => {
                                                    setTerm(item);
                                                    saveSearch(item);
                                                    setOpen(false);
                                                    router.get(route('search'), { q: item });
                                                }}
                                                className="group flex cursor-pointer items-center justify-between rounded-xl px-2.5 py-1.5 text-sm hover:bg-ink-raised"
                                            >
                                                <span className="flex items-center gap-2 truncate text-ink-text">
                                                    <span className="text-xs text-ink-faint">◷</span>
                                                    {item}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={(e) => removeRecent(item, e)}
                                                    className="opacity-0 group-hover:opacity-100 text-ink-faint hover:text-ink-expl p-1"
                                                    aria-label="Remove search"
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {trending.length > 0 && (
                                <div>
                                    <p className="mb-2 text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-faint">
                                        Trending tags
                                    </p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {trending.map((t) => (
                                            <button
                                                key={t.tag || t.label}
                                                type="button"
                                                onClick={() => {
                                                    const tagText = `#${t.tag || t.name || t.label.replace(/^#/, '')}`;
                                                    setTerm(tagText);
                                                    saveSearch(tagText);
                                                    go(t.url || route('gallery', { tag: t.tag || t.name, window: 'all' }));
                                                }}
                                                className="chip hover:border-ink-brand hover:text-ink-brand"
                                            >
                                                {t.label}
                                                {t.hint && <span className="text-[10.5px] opacity-60">({t.hint})</span>}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="rounded-xl border border-ink-line/50 bg-ink-raised/40 p-2.5 text-[11.5px] text-ink-muted">
                                <p className="font-semibold text-ink-text">Search syntax</p>
                                <div className="mt-1.5 grid grid-cols-2 gap-1 text-[11px]">
                                    <span><code className="text-ink-brand">by:artist</code> works by artist</span>
                                    <span><code className="text-ink-brand">type:comic</code> series only</span>
                                    <span><code className="text-ink-brand">#lineart</code> tag lookup</span>
                                    <span><code className="text-ink-brand">-3d</code> exclude tag</span>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <>
                            {/* Scope filter pills */}
                            <div className="flex items-center gap-1 border-b border-ink-line px-1 pb-2">
                                {SCOPES.map((s) => (
                                    <button
                                        key={s.key}
                                        type="button"
                                        onClick={() => {
                                            setScope(s.key);
                                            setActive(-1);
                                        }}
                                        className={`rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors ${
                                            scope === s.key
                                                ? 'bg-ink-brand text-[#1c1017]'
                                                : 'text-ink-muted hover:bg-ink-raised hover:text-ink-text'
                                        }`}
                                    >
                                        {s.label}
                                    </button>
                                ))}
                            </div>

                            {trimmed.length < 2 ? (
                                <p className="px-3 py-4 text-[12.5px] text-ink-muted">Keep typing — at least 2 characters.</p>
                            ) : loading && filteredRows.length === 0 ? (
                                <p className="px-3 py-4 text-[12.5px] text-ink-muted">Searching…</p>
                            ) : filteredRows.length === 0 ? (
                                <p className="px-3 py-4 text-[12.5px] text-ink-muted">Nothing found in this scope for “{trimmed}”.</p>
                            ) : (
                                <div className="mt-1 space-y-0.5">
                                    {filteredRows.map((row, i) => (
                                        <div key={`${row.group}-${row.kind}-${row.url}-${i}`}>
                                            {(i === 0 || filteredRows[i - 1].group !== row.group) && (
                                                <p className="px-2 pb-1 pt-2.5 text-[10px] font-extrabold uppercase tracking-[0.12em] text-ink-faint">
                                                    {row.group}
                                                </p>
                                            )}
                                            <button
                                                type="button"
                                                role="option"
                                                aria-selected={active === i}
                                                onMouseEnter={() => setActive(i)}
                                                onClick={() => go(row.url, row.label)}
                                                className={`flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left transition-colors ${
                                                    active === i ? 'bg-ink-raised' : 'hover:bg-white/[0.03]'
                                                }`}
                                            >
                                                {row.avatar ? (
                                                    <img
                                                        src={row.avatar}
                                                        alt=""
                                                        className={`h-8 w-8 shrink-0 object-cover ${row.kind === 'artist' ? 'rounded-full' : 'rounded-md'}`}
                                                    />
                                                ) : (
                                                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-ink-raised text-ink-faint">
                                                        {row.kind === 'tag' ? '#' : <Icon name="search" className="h-4 w-4" />}
                                                    </span>
                                                )}
                                                <span className="min-w-0 flex-1">
                                                    <span className="flex items-center gap-1.5 truncate text-[13.5px] font-semibold text-ink-text">
                                                        {row.label}
                                                        {row.is_external && (
                                                            <span className="shrink-0 rounded bg-amber-500/15 px-1 py-0.5 text-[9.5px] font-bold text-amber-300">
                                                                External ↗
                                                            </span>
                                                        )}
                                                        {row.badge && (
                                                            <span className="shrink-0 rounded bg-white/10 px-1 py-0.5 text-[9.5px] font-semibold text-ink-muted">
                                                                {row.badge}
                                                            </span>
                                                        )}
                                                    </span>
                                                    <span className="block truncate text-[11.5px] text-ink-muted">{row.hint}</span>
                                                </span>
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <button
                                type="button"
                                onClick={submit}
                                className="mt-2 flex w-full items-center gap-2 rounded-xl border-t border-ink-line px-2.5 py-2.5 text-left text-[13px] font-semibold text-ink-brand hover:bg-ink-raised"
                            >
                                <Icon name="search" className="h-4 w-4" />
                                See all results for “{trimmed}”
                            </button>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
