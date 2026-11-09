import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { Icon } from '@/Components/Icons';
import { searchFragment, selectSearchSuggestion } from '@/Lib/searchQuery';

export default function SearchBox({ initial = '', value, onChange, onSearch, scope = 'gallery', autoFocus = false, className = '' }) {
    const [localTerm, setLocalTerm] = useState(initial);
    const term = value ?? localTerm;
    const change = onChange ?? setLocalTerm;
    const [open, setOpen] = useState(false);
    const [rows, setRows] = useState([]);
    const [active, setActive] = useState(-1);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const box = useRef(null);
    const input = useRef(null);
    const listId = useId();
    const fragment = scope === 'gallery'
        ? searchFragment(term).replace(/^(?:-?tag:|by:|artist:|#)/, '').replace(/^"|"$/g, '')
        : term.trim();
    const label = scope === 'comics' ? 'Search comics' : scope === 'knowledge' ? 'Search tags & wiki' : 'Search';

    useEffect(() => {
        if (value === undefined) setLocalTerm(initial);
    }, [initial]);

    useEffect(() => {
        const outside = (event) => { if (!box.current?.contains(event.target)) setOpen(false); };
        document.addEventListener('mousedown', outside);
        return () => document.removeEventListener('mousedown', outside);
    }, []);

    useEffect(() => {
        const focusSearch = (event) => {
            const element = document.activeElement;
            if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(element?.tagName) && !element?.isContentEditable) {
                event.preventDefault();
                input.current?.focus();
            }
        };
        window.addEventListener('keydown', focusSearch);
        return () => window.removeEventListener('keydown', focusSearch);
    }, []);

    useEffect(() => {
        setRows([]);
        setActive(-1);
        setError('');
        if (!open || fragment.length < 2) { setLoading(false); return; }
        const controller = new AbortController();
        setLoading(true);
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(`${route('search.suggest')}?q=${encodeURIComponent(fragment)}&scope=${scope}`, {
                    headers: { Accept: 'application/json' }, signal: controller.signal,
                });
                if (!response.ok) throw new Error('Suggestions unavailable');
                const data = await response.json();
                setRows([
                    ...(data.tags ?? []).map((row) => ({ ...row, kind: 'tag' })),
                    ...(data.people ?? []).map((row) => ({ ...row, kind: 'artist' })),
                    ...(data.external_artists ?? []).map((row) => ({ ...row, kind: 'external_artist' })),
                    ...(data.works ?? []).map((row) => ({ ...row, kind: 'work' })),
                    ...(data.comics ?? []).map((row) => ({ ...row, kind: 'comic' })),
                    ...(data.articles ?? []).map((row) => ({ ...row, kind: 'wiki' })),
                ]);
            } catch (error) {
                if (!controller.signal.aborted) setError('Could not load suggestions. You can still search with Go.');
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 200);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [fragment, open, scope]);

    const select = (row) => {
        change(scope === 'gallery' ? selectSearchSuggestion(term, row) : (row.tag || row.label));
        input.current?.focus();
        setOpen(false);
        setActive(-1);
    };
    const go = () => {
        setOpen(false);
        if (onSearch) onSearch(term.trim());
        else if (scope === 'comics') router.get(route('comics.index'), { q: term.trim() });
        else if (scope === 'knowledge') router.get(route('knowledge.index'), { q: term.trim() });
        else router.get(route('gallery'), { q: term.trim(), window: 'all' });
    };

    return (
        <div ref={box} className={`relative ${className}`}>
            <form role="search" onSubmit={(event) => { event.preventDefault(); go(); }} className="flex min-w-0 gap-2">
                <div className="flex min-w-0 flex-1 items-center gap-2 rounded-xl border border-ink-line bg-ink-raised px-3 text-ink-muted focus-within:border-ink-brand">
                    <Icon name="search" className="h-4 w-4 shrink-0" />
                    <input ref={input} name="q" value={term} autoFocus={autoFocus} aria-label={label}
                        role="combobox" aria-autocomplete="list" aria-expanded={open && fragment.length >= 2} aria-controls={listId}
                        aria-activedescendant={active >= 0 ? `${listId}-${active}` : undefined}
                        autoComplete="off" placeholder={scope === 'gallery' ? 'Search words, #tags, by:artist…' : `${label}…`}
                        maxLength={scope === 'gallery' ? undefined : 160}
                        className="min-h-11 min-w-0 w-full border-0 bg-transparent px-0 text-sm text-ink-text focus:ring-0"
                        onChange={(event) => { change(event.target.value); setOpen(true); }}
                        onFocus={() => setOpen(true)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && open && active >= 0 && rows[active]) {
                                event.preventDefault();
                                select(rows[active]);
                            } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                                event.preventDefault();
                                setOpen(true);
                                setActive((index) => event.key === 'ArrowDown' ? Math.min(rows.length - 1, index + 1) : Math.max(-1, index - 1));
                            } else if (event.key === 'Escape') setOpen(false);
                        }} />
                </div>
                <button type="submit" className="btn-primary shrink-0">Go</button>
            </form>
            {open && fragment.length >= 2 && <div id={listId} role="listbox" aria-label="Search suggestions"
                className="absolute inset-x-0 top-[calc(100%+6px)] z-50 max-h-[45vh] overflow-auto rounded-xl border border-ink-line bg-ink-surface p-2 shadow-xl">
                {error ? <p role="alert" className="p-2 text-sm text-ink-muted">{error}</p>
                    : loading ? <p role="status" className="p-2 text-sm text-ink-muted">Loading suggestions…</p>
                        : rows.length === 0 ? <p className="p-2 text-sm text-ink-muted">{fragment.length < 2 ? 'Add words or tags, then click Go when ready.' : 'No suggestions. Click Go to search your query.'}</p>
                            : rows.map((row, index) => <button type="button" key={`${row.kind}-${row.url}`} id={`${listId}-${index}`}
                                role="option" aria-selected={index === active} onClick={() => select(row)}
                                className={`block w-full rounded-lg p-2 text-left text-sm hover:bg-ink-raised ${active === index ? 'bg-ink-raised' : ''}`}>
                                <span className="block font-semibold">{row.label}</span>
                                <span className="block text-xs text-ink-muted">{row.hint} · Add to search</span>
                            </button>)}
            </div>}
        </div>
    );
}
