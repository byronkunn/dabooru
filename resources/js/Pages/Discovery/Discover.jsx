import WorkLink from '@/Components/WorkLink';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import FeedTabs from '@/Components/FeedTabs';
import { WorkCell } from '@/Components/Work';
import { AdminPagination } from '@/Components/AdminUi';
import AdSlot from '@/Components/AdSlot';

export default function Discover({ posts, personalized, staffPicks = [], nextPage, previousPage, auth }) {
    return (
        <AppLayout>
            <Head title="Discover" />
            <TopBar title="Discover" subtitle="For you, popular now, and on the rise" tabs={<FeedTabs />} />
            <div className="space-y-4 p-3 md:p-5">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-sm font-bold">{personalized ? 'For you' : 'Popular and rising'}</h2>
                    <details className="relative text-right text-xs text-ink-muted">
                        <summary className="cursor-pointer py-1 font-semibold">About recommendations</summary>
                        <div className="absolute right-0 z-20 mt-1 w-64 max-w-[calc(100vw-2rem)] rounded-xl border border-ink-line bg-ink-surface p-3 text-left shadow-xl">
                            <p>Recommendations use recent likes, followed artists and tags, and rising popularity. Already-liked works and your own uploads stay out of the feed.</p>
                            <Link href={route('following')} className="mt-2 inline-block text-ink-brand hover:underline">Manage your follows</Link>
                        </div>
                    </details>
                </div>
                {staffPicks.length > 0 && <section className="space-y-2 rounded-xl border border-ink-line p-3" aria-labelledby="staff-picks">
                    <h2 id="staff-picks" className="text-sm font-bold">Staff Picks</h2>
                    {staffPicks.map((shelf, index) => <details key={shelf.id} open={index === 0}>
                        <summary className="cursor-pointer py-1 text-sm font-semibold text-ink-brand">{shelf.title}</summary>
                        {shelf.description && <p className="mb-2 text-xs text-ink-muted">{shelf.description}</p>}
                        <div className="flex gap-2 overflow-x-auto pb-2">
                            {shelf.items.map((item) => {
                                const Destination = item.kind === 'Work' ? WorkLink : Link;
                                return <Destination key={item.url} href={item.url} className="w-32 shrink-0 overflow-hidden rounded-lg border border-ink-line bg-ink-raised">
                                    {item.image && <img src={item.image} alt="" loading="lazy" className="h-16 w-full object-cover" />}
                                    <span className="block p-2">
                                        <span className="block text-xs text-ink-muted">{item.kind}</span>
                                        <span className="line-clamp-2 break-words text-xs font-semibold">{item.label}</span>
                                    </span>
                                </Destination>;
                            })}
                        </div>
                    </details>)}
                </section>}
                {!auth?.user && (
                    <section className="mb-3 rounded-2xl border border-ink-brand/35 bg-ink-brand/[0.07] px-4 py-3.5">
                        <p className="text-[14px] font-bold">Follow the artists you like</p>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            Make an account to follow artists and tags, save work, and get a feed built around what you
                            actually look at. It takes about a minute.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Link href={route('register')} className="btn-primary min-h-10 px-4 text-[13px]">Create an account</Link>
                            <Link href={route('login')} className="btn-ghost min-h-10 px-4 text-[13px]">Log in</Link>
                        </div>
                    </section>
                )}
                <div className="columns-2 gap-2.5 md:columns-3 md:gap-3.5">
                    {posts.map((post) => <div key={post.id} className="break-inside-avoid">
                        <p className="mb-1 flex items-center gap-1.5 px-1 text-[11px] text-ink-muted">
                            <span className="truncate">{post.recommendation_reasons.join(' · ')}</span>
                            <button
                                type="button"
                                onClick={() => router.post(route('discover.show-less'), { post: post.id }, { preserveScroll: true })}
                                className="ml-auto shrink-0 rounded px-1 text-[10.5px] font-semibold text-ink-faint hover:text-ink-text"
                                title="Show less like this"
                                aria-label={`Show less like this: ${post.title ?? 'work'}`}
                            >
                                Show less
                            </button>
                        </p>
                        <WorkCell post={post} />
                    </div>)}
                </div>
                {posts.length === 0 && <p className="py-12 text-center text-sm text-ink-muted">No new visible recommendations right now. <Link href={route('gallery')} className="text-ink-brand hover:underline">Browse the gallery</Link></p>}
                <AdSlot className="xl:hidden" placement="discover-mobile" compact />
                <AdminPagination previousPage={previousPage} nextPage={nextPage} />
            </div>
        </AppLayout>
    );
}
