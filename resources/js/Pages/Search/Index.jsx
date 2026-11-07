import { Head, Link, router } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Masonry, SeriesCard } from '@/Components/Work';
import SearchBox from '@/Components/SearchBox';
import { compact } from '@/Lib/format';

const TABS = [
    ['works', 'Works'],
    ['comics', 'Comics'],
    ['people', 'People'],
];

export default function SearchIndex({ term, tab, searchable, counts, works, comics, people, artist, type, artistOptions, minLength }) {
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
                {(artist || type) && (
                    <div className="mt-2 flex flex-wrap gap-1.5 text-[12.5px] text-ink-muted">
                        {artist && <span className="chip-plain">Artist: {artist.name ?? artist.username}</span>}
                        {type && <span className="chip-plain capitalize">Type: {type}</span>}
                    </div>
                )}
            </div>

            {!hasTerm ? (
                <div className="px-5 py-16 text-center">
                    <p className="text-[15px] text-ink-muted">Search for works, artists or comics.</p>
                    <p className="mt-1 text-[13px] text-ink-faint">Press / from anywhere to jump to the search box.</p>
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
