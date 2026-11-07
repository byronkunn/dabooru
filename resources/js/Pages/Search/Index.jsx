import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Masonry, SeriesCard } from '@/Components/Work';
import SearchBox from '@/Components/SearchBox';
import { compact } from '@/Lib/format';

const TABS = [
    ['works', 'Works'],
    ['comics', 'Comics'],
    ['people', 'People'],
];

export default function SearchIndex({ term, tab, searchable, counts, works, comics, people, artist, type, artistOptions, parsed, minLength, workFilters }) {
    const { auth } = usePage().props;
    const follow = useForm({ name: '' });
    const go = (params) => {
        router.get(route('search'), { q: term, ...params }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const total = counts.works + counts.comics + counts.people;
    const hasTerm = term.length > 0;

    const tabs = (
        <div className="scrollbar-none flex gap-1.5 overflow-x-auto px-3.5 pb-3 md:px-5" role="tablist" aria-label="Result type">
            {TABS.map(([key, label]) => (
                <button key={key} type="button" role="tab" aria-selected={tab === key} onClick={() => go({ tab: key })} className="chip">
                    {label}
                    <span className={tab === key ? 'opacity-70' : 'text-ink-faint'}>{compact(counts[key])}</span>
                </button>
            ))}
            {(artist || type) && (
                <button type="button" onClick={() => go({ artist: null, type: null })} className="chip">
                    Clear filters ✕
                </button>
            )}
        </div>
    );

    return (
        <AppLayout>
            <Head title={hasTerm ? `Search: ${term}` : 'Search'} />
            <TopBar title="Search" subtitle={hasTerm && searchable ? `${total.toLocaleString()} results for “${term}”` : undefined} tabs={tabs} />

            <div className="border-b border-ink-line px-3.5 py-3 md:px-5">
                <SearchBox initial={term} autoFocus={!hasTerm} />
                {(artist || type || parsed?.tags?.length || parsed?.exclude_tags?.length || parsed?.rating) && (
                    <div className="mt-2 flex flex-wrap items-center gap-1.5 text-[12.5px] text-ink-muted">
                        <span className="text-xs text-ink-faint">Active filters:</span>
                        {artist && <span className="chip-plain">Artist: {artist.name ?? artist.username}</span>}
                        {type && <span className="chip-plain capitalize">Format: {type}</span>}
                        {parsed?.rating && <span className="chip-plain uppercase">Rating: {parsed.rating}</span>}
                        {parsed?.tags?.map((t) => (
                            <span key={t} className="chip-plain text-ink-brand">#{t}</span>
                        ))}
                        {parsed?.exclude_tags?.map((t) => (
                            <span key={t} className="chip-plain text-[#ff98a9]">Excluding: #{t}</span>
                        ))}
                        <Link href={route('search')} className="text-xs text-ink-muted hover:text-ink-text ml-1">
                            Clear all
                        </Link>
                    </div>
                )}
            </div>

            {auth.user && <div className="border-b border-ink-line p-3.5 md:px-5">
                <Link href={route('saved-searches.index')} className="link-brand text-sm">Saved searches</Link>
                {searchable && tab === 'works' && <details className="mt-2">
                    <summary className="cursor-pointer text-sm font-semibold">Follow this search</summary>
                    <form className="mt-2 space-y-2" onSubmit={(event) => {
                        event.preventDefault();
                        follow.transform((data) => ({ ...data, ...workFilters }));
follow.post(route('saved-searches.store'));
                    }}>
                        <label className="block text-sm">Feed name
                            <input className="field-input mt-1" value={follow.data.name} onChange={(event) => follow.setData('name', event.target.value)} maxLength={80} required />
                        </label>
                        {Object.values(follow.errors).map((error) => <p key={error} role="alert" className="field-hint text-ink-expl">{error}</p>)}
                        <p className="text-xs text-ink-muted">Using an existing feed name replaces its search.</p>
                        <button className="btn-primary" disabled={follow.processing}>Save search</button>
                    </form>
                </details>}
            </div>}

            {!hasTerm ? (
                <div className="mx-auto max-w-lg px-5 py-14 text-center">
                    <p className="text-[17px] font-bold text-ink-text">Search works, artists, or comics</p>
                    <p className="mt-1 text-[13.5px] text-ink-muted">Press <kbd className="rounded border border-ink-line bg-ink-raised px-1.5 py-0.5 text-xs font-mono text-ink-text">/</kbd> from anywhere to focus the search bar.</p>

                    <div className="mt-6 rounded-2xl border border-ink-line bg-ink-raised/50 p-4 text-left">
                        <p className="text-[11px] font-extrabold uppercase tracking-wider text-ink-faint">Power Search Shortcuts</p>
                        <div className="mt-2.5 space-y-2 text-xs">
                            <div className="flex items-center justify-between">
                                <Link href={route('search', { q: 'type:comic' })} className="chip-plain hover:border-ink-brand hover:text-ink-brand">type:comic</Link>
                                <span className="text-ink-muted">Find comic series & chapters</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <Link href={route('search', { q: 'type:video' })} className="chip-plain hover:border-ink-brand hover:text-ink-brand">type:video</Link>
                                <span className="text-ink-muted">Filter by video clips</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <Link href={route('search', { q: 'rating:sfw' })} className="chip-plain hover:border-ink-brand hover:text-ink-brand">rating:sfw</Link>
                                <span className="text-ink-muted">Only all-ages clean art</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <Link href={route('search', { q: '#lineart' })} className="chip-plain hover:border-ink-brand hover:text-ink-brand">#lineart</Link>
                                <span className="text-ink-muted">Search works with specific tag</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <Link href={route('search', { q: '-sketch' })} className="chip-plain hover:border-ink-brand hover:text-ink-brand">-sketch</Link>
                                <span className="text-ink-muted">Exclude works matching a tag</span>
                            </div>
                        </div>
                    </div>
                </div>
            ) : !searchable ? (
                <div className="px-5 py-16 text-center">
                    <p className="text-[15px] text-ink-muted">Type at least {minLength} characters to search.</p>
                </div>
            ) : total === 0 ? (
                <div className="px-5 py-16 text-center">
                    <p className="text-[15px] text-ink-muted">Nothing found for “{term}”.</p>
                    <p className="mt-1 text-[13px] text-ink-faint">Try a shorter word, an artist handle, or a comic title.</p>
                </div>
            ) : (
                <>
                    {tab === 'works' && (
                        <section className="px-2.5 py-3 md:px-5">
                            {works.length === 0 ? (
                                <p className="py-12 text-center text-sm text-ink-muted">No works match.</p>
                            ) : (
                                <Masonry posts={works} />
                            )}
                            {counts.works > works.length && (
                                <p className="py-4 text-center text-[12.5px] text-ink-muted">
                                    Showing {works.length} of {compact(counts.works)} works — narrow the search to see more.
                                </p>
                            )}
                        </section>
                    )}

                    {tab === 'comics' && (
                        <section className="p-3.5 md:p-5">
                            {comics.length === 0 ? (
                                <p className="py-12 text-center text-sm text-ink-muted">No series match.</p>
                            ) : (
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:gap-4 xl:grid-cols-4">
                                    {comics.map((comic) => (
                                        <SeriesCard key={comic.id} comic={comic} />
                                    ))}
                                </div>
                            )}
                        </section>
                    )}

                    {tab === 'people' && (
                        <section>
                            {people.length === 0 ? (
                                <p className="py-12 text-center text-sm text-ink-muted">No artists match.</p>
                            ) : (
                                people.map((person) => (
                                    <Link
                                        key={person.id}
                                        href={route('profile.show', person.username)}
                                        className="flex items-center gap-3 border-b border-ink-line px-4 py-3.5 hover:bg-white/[0.02] md:px-5"
                                    >
                                        <img src={person.avatar} alt="" className="h-11 w-11 shrink-0 rounded-full bg-ink-raised object-cover" />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-[14.5px] font-bold">{person.name}</span>
                                            <span className="block truncate text-[13px] text-ink-muted">@{person.username}</span>
                                            {person.bio && <span className="mt-0.5 block truncate text-[12.5px] text-ink-faint">{person.bio}</span>}
                                        </span>
                                        <span className="shrink-0 text-right text-[12px] text-ink-muted">
                                            <span className="block">{compact(person.posts_count)} works</span>
                                            <span className="block">{compact(person.followers)} followers</span>
                                        </span>
                                    </Link>
                                ))
                            )}
                        </section>
                    )}

                    {artistOptions.length > 1 && tab === 'works' && (
                        <section className="border-t border-ink-line px-4 py-4 md:px-5">
                            <p className="mb-2 text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-faint">
                                Results by artist
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                {artistOptions.map((option) => (
                                    <button
                                        key={option.username}
                                        type="button"
                                        onClick={() => go({ artist: option.username })}
                                        className={`chip-plain ${artist?.username === option.username ? 'border-ink-brand text-ink-brand' : ''}`}
                                    >
                                        {option.name}
                                    </button>
                                ))}
                            </div>
                        </section>
                    )}
                </>
            )}
        </AppLayout>
    );
}
