import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/Components/Icons';
import { clearedGalleryFilters, durationAtPosition, durationLabel, durationPosition, durationStops } from '@/Lib/galleryFilters';

/* --------------------------------------------------------------- primitives
   The reference panel is a flat list, not a grid of cards: a small section
   heading, then rows separated by hairlines, with the active row marked by a
   left accent bar and brand-coloured text. */

function OptionList({ name, options, value, onChange }) {
    return (
        <ul className="divide-y divide-ink-line/70" role="radiogroup" aria-label={name}>
            {options.map((opt) => {
                const active = value === opt.value;
                return (
                    <li key={opt.value}>
                        <button
                            type="button"
                            role="radio"
                            aria-checked={active}
                            onClick={() => onChange(opt.value)}
                            className={`relative flex min-h-[40px] w-full items-center gap-2 py-2 pl-3 pr-2 text-left text-[14px] transition-colors hover:bg-ink-raised/60 ${
                                active ? 'font-bold text-ink-brand' : 'text-ink-text'
                            }`}
                        >
                            {/* left accent bar on the selected row */}
                            <span
                                aria-hidden="true"
                                className={`absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-r ${active ? 'bg-ink-brand' : 'bg-transparent'}`}
                            />
                            <span className="truncate">{opt.label}</span>
                            {opt.hint && <span className="ml-auto shrink-0 text-[11.5px] font-normal text-ink-faint">{opt.hint}</span>}
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

/* ------------------------------------------------------------ duration slider
   A real two-handle range so it behaves like the reference: keyboard-accessible,
   clamped so the handles cannot cross. */
function DurationRange({ filters, onApply, disabled }) {
    const [value, setValue] = useState([filters.min_duration, filters.max_duration]);
    const [error, setError] = useState('');
    const draft = useRef(value);
    const dirty = useRef(false);

    useEffect(() => {
        draft.current = [filters.min_duration, filters.max_duration];
        setValue(draft.current);
        dirty.current = false;
        setError('');
    }, [filters.min_duration, filters.max_duration, filters.format]);

    const change = (next) => {
        draft.current = next;
        dirty.current = true;
        setValue(next);
        setError('');
    };
    const commit = () => {
        if (!dirty.current || disabled) return;
        const [min, max] = draft.current;
        if ([min, max].some((n) => n !== '' && (!Number.isInteger(Number(n)) || Number(n) < 1 || Number(n) > 86400))) {
            setError('Enter whole seconds from 1 to 86400, or leave blank for Any.');
            return;
        }
        if (max !== '' && Number(min) > Number(max)) {
            setError('Minimum length must not exceed maximum length.');
            return;
        }
        dirty.current = false;
        onApply({ min_duration: min === '' ? '' : Number(min), max_duration: max === '' ? '' : Number(max) });
    };
    const from = durationPosition(value[0]);
    const to = durationPosition(value[1], true);
    const tickLabels = ['Any', '15s', '30s', '1m', '2m', '5m', '10m', '25m', 'Any'];
    const moveHandle = (index, position) => {
        const limited = index === 0
            ? Math.min(position, to, durationPosition(86400))
            : Math.max(position, from, durationPosition(1));
        const seconds = durationAtPosition(limited);
        change(index === 0 ? [seconds, value[1]] : [value[0], seconds]);
    };
    const moveWithKeyboard = (index, event) => {
        const position = index === 0 ? from : to;
        const next = {
            ArrowRight: Math.floor(position) + 1, ArrowUp: Math.floor(position) + 1,
            ArrowLeft: Math.ceil(position) - 1, ArrowDown: Math.ceil(position) - 1,
            Home: 0, End: 8,
        }[event.key];
        if (next === undefined) return;
        event.preventDefault();
        moveHandle(index, Math.max(0, Math.min(8, next)));
    };

    return (
        <fieldset disabled={disabled} className="min-w-0 px-1.5 pt-1 disabled:opacity-40">
            <p className="mb-2 text-[13px] font-semibold text-ink-brand" aria-live="polite">{durationLabel(value[0], value[1])}</p>
            <div className="relative mx-2 h-7">
                <div className="absolute top-1/2 h-1 w-full -translate-y-1/2 rounded-full bg-[#3a3242]" />
                <div
                    className="absolute top-1/2 h-1 -translate-y-1/2 rounded-full bg-ink-brand"
                    style={{ left: `${from / 8 * 100}%`, width: `${Math.max(0, to - from) / 8 * 100}%` }}
                />
                {durationStops.map((_, index) => <span key={index} aria-hidden="true" className="absolute top-2 h-3 w-px bg-ink-muted" style={{ left: `${index / 8 * 100}%` }} />)}
                <input
                    type="range"
                    min={0}
                    max={8}
                    step="any"
                    value={from}
                    aria-label="Shortest duration"
                    aria-valuetext={value[0] ? `${value[0]} seconds or more` : 'No minimum'}
                    onChange={(e) => moveHandle(0, Number(e.target.value))}
                    onKeyDown={(e) => moveWithKeyboard(0, e)}
                    onPointerUp={commit}
                    onKeyUp={commit}
                    onBlur={commit}
                    className="range-thumb absolute inset-0 w-full appearance-none bg-transparent"
                />
                <input
                    type="range"
                    min={0}
                    max={8}
                    step="any"
                    value={to}
                    aria-label="Longest duration"
                    aria-valuetext={value[1] ? `Up to ${value[1]} seconds` : 'No maximum'}
                    onChange={(e) => moveHandle(1, Number(e.target.value))}
                    onKeyDown={(e) => moveWithKeyboard(1, e)}
                    onPointerUp={commit}
                    onKeyUp={commit}
                    onBlur={commit}
                    className="range-thumb absolute inset-0 w-full appearance-none bg-transparent"
                />
            </div>
            <div className="relative mx-2 mb-5 h-4 text-[10px] text-ink-faint">
                {tickLabels.map((label, index) => <span key={index} className="absolute -translate-x-1/2 whitespace-nowrap" style={{ left: `${index / 8 * 100}%` }}>{label}</span>)}
            </div>
            {error && <p role="alert" className="mt-2 text-xs text-ink-expl">{error}</p>}
        </fieldset>
    );
}

/* ------------------------------------------------------------------- tags
   Include and exclude share one component: type a tag, press Enter, it becomes
   a removable chip. Suggestions come from the tags actually in use. */
function TagEditor({ label, hint, tone, values, suggestions, onAdd, onRemove }) {
    const [text, setText] = useState('');
    const [focused, setFocused] = useState(false);

    const commit = (raw) => {
        const clean = raw.trim().toLowerCase().replace(/^#/, '').replace(/\s+/g, ' ').slice(0, 40);
        if (clean) onAdd(clean);
        setText('');
    };

    const matches = text.trim()
        ? suggestions.filter((t) => t.includes(text.trim().toLowerCase().replace(/^#/, '')) && !values.includes(t)).slice(0, 6)
        : suggestions.filter((t) => !values.includes(t)).slice(0, 8);

    const chipTone = tone === 'exclude'
        ? 'border-ink-expl/50 bg-ink-expl/15 text-ink-expl'
        : 'border-ink-brand/50 bg-ink-brand/15 text-ink-brand';

    return (
        <div>
            <div className="flex items-baseline justify-between">
                <h4 className="pb-1 text-[12.5px] font-bold text-ink-muted">{label}</h4>
                {hint && <span className="text-[11px] text-ink-faint">{hint}</span>}
            </div>

            {values.length > 0 && (
                <div className="mb-1.5 flex flex-wrap gap-1.5">
                    {values.map((t) => (
                        <span key={t} className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[12.5px] font-semibold ${chipTone}`}>
                            {tone === 'exclude' ? '−' : '+'} #{t}
                            <button type="button" onClick={() => onRemove(t)} aria-label={`Remove ${t}`} className="opacity-70 hover:opacity-100">
                                <Icon name="close" className="h-3 w-3" />
                            </button>
                        </span>
                    ))}
                </div>
            )}

            <div className="relative">
                <input
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    onFocus={() => setFocused(true)}
                    onBlur={() => setTimeout(() => setFocused(false), 120)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); commit(text); }
                        if (e.key === 'Backspace' && text === '' && values.length) onRemove(values[values.length - 1]);
                    }}
                    placeholder={tone === 'exclude' ? 'Type a tag to hide, then Enter' : 'Type a tag to require, then Enter'}
                    aria-label={label}
                    className="field-input min-h-[38px] w-full text-[13.5px]"
                />
                {focused && matches.length > 0 && (
                    <div className="absolute left-0 right-0 top-full z-10 mt-1 overflow-hidden rounded-xl border border-ink-line bg-ink-surface shadow-xl">
                        {matches.map((t) => (
                            <button
                                key={t}
                                type="button"
                                onMouseDown={(e) => { e.preventDefault(); commit(t); }}
                                className="block w-full px-3 py-1.5 text-left text-[13px] text-ink-text hover:bg-ink-raised"
                            >
                                #{t}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------- panel
   Changes apply immediately (like the reference, which navigates on click),
   so there is no Apply button. A "Clear" row appears once anything is active. */
export default function GalleryFilterSheet({
    open,
    onClose,
    filters,
    windowOptions,
    formatOptions,
    tagSuggestions = [],
    activeCount = 0,
    onApply,
}) {
    const set = onApply;

    const addTag = (list, tag) => {
        const next = [...(filters[list] ?? [])];
        if (!next.includes(tag)) next.push(tag);
        // a tag cannot be both required and excluded
        const opposite = list === 'tags' ? 'exclude_tags' : 'tags';
        set({ [list]: next, [opposite]: (filters[opposite] ?? []).filter((t) => t !== tag) });
    };
    const removeTag = (list, tag) => set({ [list]: (filters[list] ?? []).filter((t) => t !== tag) });

    return (
        <Dialog open={open} onClose={onClose} className="relative z-[80]">
            <div className="fixed inset-0 bg-black/70" aria-hidden="true" />

            <div className="fixed inset-0 flex items-start justify-center p-0 md:p-8">
                <DialogPanel className="flex max-h-full w-full max-w-[640px] flex-col border-x border-b border-ink-line bg-ink-surface shadow-2xl md:max-h-[calc(100vh-4rem)] md:rounded-xl md:border">
                    {/* header bar */}
                    <header className="flex shrink-0 items-center gap-2 border-b border-ink-line px-3 py-2.5">
                        <Icon name="adjustments" />
                        <DialogTitle className="text-[15px] font-bold tracking-[-0.01em]">Filters</DialogTitle>
                        {activeCount > 0 && (
                            <button
                                type="button"
                                onClick={() => onApply(clearedGalleryFilters)}
                                className="ml-1 text-[12.5px] font-semibold text-ink-brand hover:underline"
                            >
                                Clear filters
                            </button>
                        )}
                        <button type="button" onClick={onClose} aria-label="Close" className="btn-icon-flat ml-auto h-8 w-8">
                            <Icon name="close" className="h-[17px] w-[17px]" />
                        </button>
                    </header>

                    <div className="min-h-0 flex-1 overflow-y-auto px-3 pb-5 pt-3">
                        {/* FORMAT */}
                        <div className="mt-4">
                            <h4 className="pb-1 text-[12.5px] font-bold text-ink-muted">Format</h4>
                            <OptionList name="Format" options={formatOptions} value={filters.format} onChange={(v) => set({ format: v })} />
                        </div>

                        {/* TIME PERIOD */}
                        <div className="mt-4">
                            <h4 className="pb-1 text-[12.5px] font-bold text-ink-muted">Time period</h4>
                            <OptionList name="Time period" options={windowOptions} value={filters.window} onChange={(v) => set({ window: v })} />
                        </div>

                        {/* DURATION — only meaningful for video-ish formats */}
                        <div className="mt-4">
                            <h4 className="pb-1 text-[12.5px] font-bold text-ink-muted">Video length</h4>
                            <DurationRange filters={filters} onApply={set} disabled={filters.format !== 'video'} />
                            <p className="mt-1 px-1.5 text-[11px] text-ink-faint">
                                Filters all matching videos, including works not loaded yet. Any maximum means no length limit.
                            </p>
                            {filters.format !== 'video' && <button type="button" onClick={() => set({ format: 'video' })} className="mt-2 text-[13px] font-semibold text-ink-brand hover:underline">Switch to videos</button>}
                        </div>

                        {/* TAGS */}
                        <div className="mt-4 space-y-2.5">
                            <h4 className="pb-1 text-[12.5px] font-bold text-ink-muted">Tags</h4>
                            <TagEditor
                                label="Include any of"
                                tone="include"
                                values={filters.tags ?? []}
                                suggestions={tagSuggestions}
                                onAdd={(t) => addTag('tags', t)}
                                onRemove={(t) => removeTag('tags', t)}
                            />
                            <TagEditor
                                label="Must exclude"
                                hint="hides the tag"
                                tone="exclude"
                                values={filters.exclude_tags ?? []}
                                suggestions={tagSuggestions}
                                onAdd={(t) => addTag('exclude_tags', t)}
                                onRemove={(t) => removeTag('exclude_tags', t)}
                            />
                        </div>

                        <div className="mt-4">
                            <h4 className="pb-1 text-[12.5px] font-bold text-ink-muted">Star rating</h4>
                            <div role="group" aria-label="Minimum average star rating" className="flex flex-wrap items-center gap-1">
                                <button type="button" aria-pressed={!filters.min_stars}
                                    onClick={() => set({ min_stars: '' })}
                                    className={`min-h-11 rounded-lg px-3 text-sm font-semibold ${!filters.min_stars ? 'bg-ink-raised text-ink-brand' : 'text-ink-muted'}`}>
                                    Any
                                </button>
                                {[1, 2, 3, 4, 5].map((stars) => (
                                    <button key={stars} type="button" aria-label={`${stars} ${stars === 1 ? 'star' : 'stars'} and up`}
                                        aria-pressed={Number(filters.min_stars) === stars}
                                        onClick={() => set({ min_stars: stars })}
                                        className={`grid h-11 w-11 place-items-center rounded-lg hover:bg-ink-raised focus-visible:outline focus-visible:outline-ink-brand ${Number(filters.min_stars) >= stars ? 'text-ink-brand' : 'text-ink-muted'}`}>
                                        <Icon name={Number(filters.min_stars) >= stars ? 'starFilled' : 'star'} className="h-7 w-7" />
                                    </button>
                                ))}
                            </div>
                            <p className="mt-1 px-1.5 text-[13px] font-semibold">
                                {filters.min_stars ? `${filters.min_stars} stars and up` : 'Any star rating'}
                            </p>
                            <p className="mt-1 px-1.5 text-[11px] text-ink-faint">
                                Uses the average user rating, not likes or views. Any includes unrated works.
                            </p>
                        </div>

                        {/* CONTENT */}
                        <div className="mt-4">
                            <h4 className="pb-1 text-[12.5px] font-bold text-ink-muted">Content</h4>
                            <ul className="divide-y divide-ink-line/70">
                                <li>
                                    <button
                                        type="button"
                                        role="switch"
                                        aria-checked={filters.explicit}
                                        onClick={() => set({ explicit: !filters.explicit })}
                                        className="relative flex min-h-[40px] w-full items-center gap-3 py-2 pl-3 pr-2 text-left text-[14px] hover:bg-ink-raised/60"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className={`absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-r ${filters.explicit ? 'bg-ink-brand' : 'bg-transparent'}`}
                                        />
                                        <span className={filters.explicit ? 'font-bold text-ink-brand' : 'text-ink-text'}>Show explicit</span>
                                        <span className="ml-auto text-[11.5px] text-ink-faint">{filters.explicit ? 'Shown' : 'Hidden'}</span>
                                    </button>
                                </li>
                                {filters.tag && (
                                    <li>
                                        <button
                                            type="button"
                                            onClick={() => set({ tag: '' })}
                                            className="relative flex min-h-[40px] w-full items-center gap-3 py-2 pl-3 pr-2 text-left text-[14px] hover:bg-ink-raised/60"
                                        >
                                            <span aria-hidden="true" className="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-r bg-ink-brand" />
                                            <span className="font-bold text-ink-brand">#{filters.tag}</span>
                                            <span className="ml-auto text-[11.5px] font-semibold text-ink-faint">Clear tag</span>
                                        </button>
                                    </li>
                                )}
                            </ul>
                        </div>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    );
}

/** Counts filtering categories, independently of sorting and content preference. */
export function GalleryFilterButton({ onClick, activeCount = 0, expanded = false }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-expanded={expanded}
            aria-label={activeCount ? `Filters, ${activeCount} active` : 'Filters'}
            title="Filters"
            className={`btn-icon-flat relative h-10 w-10 shrink-0 ${activeCount ? 'text-ink-brand' : ''}`}
        >
            <Icon name="adjustments" className="h-[20px] w-[20px]" />
            {activeCount > 0 && (
                <span className="absolute right-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-ink-brand px-1 text-[10px] font-extrabold leading-none text-[#1c1017]">
                    {activeCount}
                </span>
            )}
        </button>
    );
}
