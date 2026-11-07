import { Dialog, DialogPanel } from '@headlessui/react';
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/Components/Icons';

/* --------------------------------------------------------------- primitives
   The reference panel is a flat list, not a grid of cards: a small section
   heading, then rows separated by hairlines, with the active row marked by a
   left accent bar and brand-coloured text. */

function SectionHeading({ children }) {
    return (
        <h3 className="flex items-center gap-1.5 border-b border-ink-line pb-1.5 text-[11px] font-bold uppercase tracking-[0.11em] text-ink-faint">
            {children}
        </h3>
    );
}

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
function DurationRange({ min, max, value, onChange }) {
    const [from, to] = value;
    const step = 5;
    const pct = (n) => ((n - min) / (max - min)) * 100;

    return (
        <div className="px-1.5 pt-1">
            <div className="relative h-7">
                <div className="absolute top-1/2 h-1 w-full -translate-y-1/2 rounded-full bg-[#3a3242]" />
                <div
                    className="absolute top-1/2 h-1 -translate-y-1/2 rounded-full bg-ink-brand"
                    style={{ left: `${pct(from)}%`, width: `${Math.max(0, pct(to) - pct(from))}%` }}
                />
                <input
                    type="range"
                    min={min}
                    max={max}
                    step={step}
                    value={from}
                    aria-label="Shortest duration"
                    aria-valuetext={`${from} seconds or more`}
                    onChange={(e) => onChange([Math.min(Number(e.target.value), to - step), to])}
                    className="range-thumb absolute inset-0 w-full appearance-none bg-transparent"
                />
                <input
                    type="range"
                    min={min}
                    max={max}
                    step={step}
                    value={to}
                    aria-label="Longest duration"
                    aria-valuetext={to >= max ? `${max} seconds or more` : `up to ${to} seconds`}
                    onChange={(e) => onChange([from, Math.max(Number(e.target.value), from + step)])}
                    className="range-thumb absolute inset-0 w-full appearance-none bg-transparent"
                />
            </div>
            <div className="mt-1 flex items-center justify-between text-[11px] text-ink-faint">
                <span>{min}s</span>
                <span className="font-semibold text-ink-muted">
                    {from === min ? 'Any' : `${from}s`} – {to >= max ? 'any' : `${to}s`}
                </span>
                <span>{max}s</span>
            </div>
        </div>
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

/** A small numeric floor, e.g. "at least 10 likes". */
function Threshold({ label, unit, value, onChange, max = 100 }) {
    const n = Number(value) || 0;
    return (
        <li className="flex items-center gap-2 py-2 pl-3 pr-2">
            <span className="text-[14px]">{label}</span>
            <div className="ml-auto flex items-center gap-1.5">
                <button type="button" onClick={() => onChange(Math.max(0, n - 5))} aria-label={`Decrease ${label}`}
                    className="grid h-7 w-7 place-items-center rounded-lg border border-ink-line text-ink-muted hover:text-ink-text">−</button>
                <input
                    type="range" min={0} max={max} step={5} value={n}
                    onChange={(e) => onChange(Number(e.target.value))}
                    aria-label={`${label} minimum`}
                    aria-valuetext={n ? `at least ${n} ${unit}` : 'no minimum'}
                    className="range-thumb w-[92px] appearance-none bg-transparent"
                />
                <button type="button" onClick={() => onChange(Math.min(max, n + 5))} aria-label={`Increase ${label}`}
                    className="grid h-7 w-7 place-items-center rounded-lg border border-ink-line text-ink-muted hover:text-ink-text">+</button>
                <span className={`w-[46px] text-right text-[12px] font-semibold ${n ? 'text-ink-brand' : 'text-ink-faint'}`}>
                    {n ? `${n}+` : 'any'}
                </span>
            </div>
        </li>
    );
}

/* ------------------------------------------------------------------- panel
   Changes apply immediately (like the reference, which navigates on click),
   so there is no Apply button. A "Clear" row appears once anything is active. */
export default function GalleryFilterSheet({
    open,
    onClose,
    filters,
    sortOptions,
    windowOptions,
    formatOptions,
    durationBounds = [0, 60],
    tagSuggestions = [],
    durationNarrowed = false,
    onApply,
}) {
    const [duration, setDuration] = useState([durationBounds[0], durationBounds[1]]);
    const durationTouched = useRef(false);

    // seed the slider from the URL when the panel opens
    useEffect(() => {
        if (!open) return;
        const [lo, hi] = durationBounds;
        setDuration([
            Number(filters.min_duration ?? lo),
            filters.max_duration === '' || filters.max_duration == null ? hi : Number(filters.max_duration),
        ]);
        durationTouched.current = false;
    }, [open, durationBounds, filters.min_duration, filters.max_duration]);

    // push a duration change once the visitor stops dragging
    useEffect(() => {
        if (!open || !durationTouched.current) return;
        const [lo, hi] = durationBounds;
        const t = setTimeout(() => {
            onApply({
                ...filters,
                min_duration: duration[0] > lo ? duration[0] : '',
                max_duration: duration[1] < hi ? duration[1] : '',
            });
        }, 450);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [duration]);

    const changeDuration = (next) => {
        durationTouched.current = true;
        setDuration(next);
    };

    const set = (patch) => onApply({ ...filters, ...patch });

    const addTag = (list, tag) => {
        const next = [...(filters[list] ?? [])];
        if (!next.includes(tag)) next.push(tag);
        // a tag cannot be both required and excluded
        const opposite = list === 'tags' ? 'exclude_tags' : 'tags';
        set({ [list]: next, [opposite]: (filters[opposite] ?? []).filter((t) => t !== tag) });
    };
    const removeTag = (list, tag) => set({ [list]: (filters[list] ?? []).filter((t) => t !== tag) });

    const activeCount = [
        filters.sort !== 'top',
        filters.window !== 'week',
        filters.format !== 'all',
        !!filters.tag,
        filters.explicit,
        !!filters.min_duration,
        filters.max_duration != null && filters.max_duration !== '',
        (filters.tags ?? []).length > 0,
        (filters.exclude_tags ?? []).length > 0,
        !!filters.min_likes,
        !!filters.min_views,
    ].filter(Boolean).length;

    return (
        <Dialog open={open} onClose={onClose} className="relative z-[80]">
            <div className="fixed inset-0 bg-black/70" aria-hidden="true" />

            <div className="fixed inset-0 flex items-start justify-center p-0 md:p-8">
                <DialogPanel className="flex max-h-full w-full max-w-[640px] flex-col border-x border-b border-ink-line bg-ink-surface shadow-2xl md:max-h-[calc(100vh-4rem)] md:rounded-xl md:border">
                    {/* header bar */}
                    <header className="flex shrink-0 items-center gap-2 border-b border-ink-line px-3 py-2.5">
                        <span className="text-[15px] font-bold tracking-[-0.01em]">Sort &amp; filter</span>
                        {activeCount > 0 && (
                            <button
                                type="button"
                                onClick={() => {
                                    durationTouched.current = false;
                                    onApply({ sort: 'top', window: 'week', format: 'all', tag: '', q: '', explicit: filters.explicit, min_duration: '', max_duration: '', tags: [], exclude_tags: [], min_likes: '', min_views: '' });
                                }}
                                className="ml-1 text-[12.5px] font-semibold text-ink-brand hover:underline"
                            >
                                Clear all
                            </button>
                        )}
                        <button type="button" onClick={onClose} aria-label="Close" className="btn-icon-flat ml-auto h-8 w-8">
                            <Icon name="close" className="h-[17px] w-[17px]" />
                        </button>
                    </header>

                    <div className="min-h-0 flex-1 overflow-y-auto px-3 pb-5 pt-3">
                        {/* SORT BY */}
                        <SectionHeading>
                            <Icon name="filter" className="h-3.5 w-3.5" />
                            Sort by
                        </SectionHeading>
                        <OptionList name="Sort by" options={sortOptions} value={filters.sort} onChange={(v) => set({ sort: v })} />

                        {/* FILTERS heading, matching the reference's second block */}
                        <div className="mt-5">
                            <SectionHeading>
                                <Icon name="filter" className="h-3.5 w-3.5" />
                                Filters
                            </SectionHeading>
                        </div>

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
                            <DurationRange min={durationBounds[0]} max={durationBounds[1]} value={duration} onChange={changeDuration} />
                            <p className="mt-1 px-1.5 text-[11px] text-ink-faint">
                                {duration[0] === durationBounds[0] && duration[1] >= durationBounds[1]
                                    ? 'Only videos have a length — setting a range switches the gallery to videos.'
                                    : durationNarrowed
                                        ? 'Showing videos only, because only videos have a length.'
                                        : 'Applies to the videos in the gallery.'}
                            </p>
                        </div>

                        {/* TAGS */}
                        <div className="mt-4 space-y-2.5">
                            <h4 className="pb-1 text-[12.5px] font-bold text-ink-muted">Tags</h4>
                            <TagEditor
                                label="Must include"
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

                        {/* QUALITY */}
                        <div className="mt-4">
                            <h4 className="pb-1 text-[12.5px] font-bold text-ink-muted">Popularity</h4>
                            <ul className="divide-y divide-ink-line/70">
                                <Threshold label="At least this many likes" unit="likes" value={filters.min_likes} onChange={(v) => set({ min_likes: v || '' })} max={50} />
                                <Threshold label="At least this many views" unit="views" value={filters.min_views} onChange={(v) => set({ min_views: v || '' })} max={150} />
                            </ul>
                            <p className="mt-1 px-1.5 text-[11px] text-ink-faint">
                                Works are scored by likes and views — there are no star ratings yet.
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

/** The icon that opens the panel — a funnel, with a count when filters are on. */
export function GalleryFilterButton({ onClick, activeCount = 0, expanded = false }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-expanded={expanded}
            aria-label={activeCount ? `Sort and filter, ${activeCount} active` : 'Sort and filter'}
            title="Sort and filter"
            className={`btn-icon-flat relative h-10 w-10 shrink-0 ${activeCount ? 'text-ink-brand' : ''}`}
        >
            <Icon name="filter" className="h-[20px] w-[20px]" />
            {activeCount > 0 && (
                <span className="absolute right-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-ink-brand px-1 text-[10px] font-extrabold leading-none text-[#1c1017]">
                    {activeCount}
                </span>
            )}
        </button>
    );
}
