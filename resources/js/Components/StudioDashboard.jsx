import { Link, router } from '@inertiajs/react';
import { AdminPagination } from '@/Components/AdminUi';

const sections = ['overview', 'posts', 'analytics', 'planner', 'comics'];
const sortLabels = { newest: 'Newest', views: 'Views', likes: 'Likes', saves: 'Saves', engagement: 'Engagement', growth: 'Fastest growth', ratings: 'Star rating' };
export const studioVisit = (filters, patch) => router.get(route('studio.index'), { ...filters, ...patch }, { preserveScroll: true });

export function StudioTabs({ filters }) {
    return (
        <nav aria-label="Studio sections" className="flex flex-wrap justify-center gap-2 border-t border-ink-line p-3">
            {sections.map((tab) => <Link key={tab} href={route('studio.index', { ...filters, tab })} aria-current={filters.tab === tab ? 'page' : undefined}
                className={`chip capitalize ${filters.tab === tab ? 'is-active' : ''}`}>{tab}</Link>)}
        </nav>
    );
}

function NumberCard({ label, total, previous }) {
    return <div className="panel min-w-0">
        <p className="text-xs text-ink-muted">{label}</p>
        <p className="mt-1 text-2xl font-bold">{total?.toLocaleString() ?? '—'}</p>
        {previous !== undefined && <p className="mt-1 text-xs text-ink-muted">{previous.toLocaleString()} previous period · {total - previous > 0 ? '+' : ''}{total - previous} change</p>}
    </div>;
}

function PerformanceCard({ post, filters }) {
    return <article className="panel min-w-0">
        <div className="flex items-start gap-3">
            {post.thumbnail && <img src={post.thumbnail} alt="" loading="lazy" className="h-16 w-16 shrink-0 rounded-lg object-cover" />}
            <div className="min-w-0">
                <Link href={post.url} className="break-words font-semibold text-ink-brand hover:underline">{post.title}</Link>
                <p className="mt-1 text-xs text-ink-muted">{post.original ? 'Original artwork' : `Shared work${post.artist ? ` · credit: ${post.artist}` : ''}`} · {post.type}</p>
            </div>
        </div>
        <p className="mt-3 text-sm">{post.periodViews} views · {post.periodLikes} likes · {post.periodSaves} saves · {post.periodComments} comments in period</p>
        <p className="mt-1 text-xs text-ink-muted">Lifetime: {post.views} views · {post.likes} likes · {post.saves} saves · {post.comments} comments</p>
        <p className="mt-1 text-xs text-ink-muted">Rating: {post.rating ?? 'Not rated'} ({post.votes} votes) · Like momentum: {post.growth > 0 ? '+' : ''}{post.growth}</p>
        <p className="mt-1 text-xs text-ink-muted">{post.interactionsPer100Views === null ? 'Not enough view data for an engagement ratio.' : `${post.interactionsPer100Views} interactions per 100 daily views`}</p>
        <Link href={route('studio.index', { ...filters, tab: 'analytics', post: post.id })} className="mt-2 inline-block min-h-11 py-2 text-sm font-semibold text-ink-brand hover:underline">View analytics</Link>
    </article>;
}

function DailyChart({ metric, dates }) {
    const max = Math.max(1, ...metric.daily.map(Math.abs));
    return <section className="panel min-w-0">
        <h3 className="font-semibold">{metric.label}</h3>
        <div className="mt-3 flex h-24 items-end gap-px" aria-hidden="true">
            {metric.daily.map((value, index) => <div key={dates[index]} title={`${dates[index]}: ${value}`}
                className={`min-w-0 flex-1 rounded-t ${value < 0 ? 'bg-red-400' : 'bg-ink-brand'}`}
                style={{ height: `${Math.max(2, Math.abs(value) / max * 100)}%`, opacity: value ? 1 : 0.15 }} />)}
        </div>
        <div className="mt-2 flex justify-between text-[11px] text-ink-muted"><span>{dates[0]}</span><span>{dates[dates.length - 1]}</span></div>
        <details className="mt-2 text-xs">
            <summary className="cursor-pointer py-2 text-ink-brand">Daily values</summary>
            <div className="max-h-48 overflow-y-auto"><table className="w-full text-left"><caption className="sr-only">{metric.label} by date</caption><thead><tr><th>Date</th><th>Count</th></tr></thead>
                <tbody>{dates.map((date, index) => <tr key={date}><td>{date}</td><td>{metric.daily[index]}</td></tr>)}</tbody></table></div>
        </details>
    </section>;
}

export default function StudioDashboard({ filters, analytics = null, performance = [], performancePages = {}, comicAnalytics = [], comicPages = {}, comicTrackingSince = null }) {
    const metrics = Object.values(analytics?.metrics ?? {});
    const update = (patch) => studioVisit(filters, patch);
    return (
        <div className="space-y-5">
            {!['planner', 'comics'].includes(filters.tab) && <div className="flex flex-wrap items-end gap-3">
                <label className="min-w-0 text-sm">Period<select className="field-input mt-1" value={filters.days} onChange={(e) => update({ days: e.target.value })}>
                    {[7, 30, 90].map((days) => <option key={days} value={days}>Last {days} days</option>)}
                </select></label>
                <label className="min-w-0 text-sm">Work ownership<select className="field-input mt-1" value={filters.ownership} onChange={(e) => update({ ownership: e.target.value, post: undefined })}>
                    <option value="all">All uploads</option><option value="original">Original artwork</option><option value="shared">Shared / credited artwork</option>
                </select></label>
                {filters.post && <button type="button" className="btn-ghost min-h-11" onClick={() => update({ post: undefined })}>Clear selected work</button>}
            </div>}
            {filters.tab === 'overview' && analytics && <>
                <h2 className="text-lg font-bold">Last {filters.days} days</h2>
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">{metrics.map((metric) => <NumberCard key={metric.label} {...metric} />)}</div>
                <h2 className="text-lg font-bold">Lifetime totals {filters.post ? '· selected work' : ''}</h2>
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">{Object.entries(analytics.lifetime).map(([label, total]) => <NumberCard key={label} label={label === 'rating' ? 'Average star rating' : label === 'followers' ? 'Account followers' : label} total={total} />)}</div>
                <div className="flex flex-wrap items-center justify-between gap-2"><h2 className="text-lg font-bold">Top posts by engagement</h2><Link href={route('studio.index', { ...filters, tab: 'posts' })} className="text-sm text-ink-brand">See all posts</Link></div>
                <div className="grid gap-3 md:grid-cols-2">{analytics.topPosts.map((post) => <PerformanceCard key={post.id} post={post} filters={filters} />)}</div>
                {!analytics.topPosts.length && <p className="text-sm text-ink-muted">Publish work to start seeing performance.</p>}
            </>}
            {filters.tab === 'posts' && <>
                <label className="block text-sm">Rank published posts<select className="field-input mt-1 max-w-xs" value={filters.sort} onChange={(e) => update({ sort: e.target.value })}>
                    {Object.entries(sortLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                </select></label>
                <p className="text-xs text-ink-muted">Views, likes, saves, and engagement use the selected period. Stars are lifetime averages with vote counts. Fastest growth compares likes in the last 24 hours with the previous 24 hours.</p>
                <div className="grid gap-3 md:grid-cols-2">{performance.map((post) => <PerformanceCard key={post.id} post={post} filters={filters} />)}</div>
                {!performance.length && <p className="text-sm text-ink-muted">No published work in this category.</p>}
                <AdminPagination {...performancePages} />
            </>}
            {filters.tab === 'analytics' && analytics && <>
                <h2 className="text-lg font-bold">{filters.post ? 'Selected work performance' : 'Performance over time'}</h2>
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">{metrics.map((metric) => <NumberCard key={metric.label} {...metric} />)}</div>
                <div className="grid gap-3 md:grid-cols-2">{metrics.map((metric) => <DailyChart key={metric.label} metric={metric} dates={analytics.dates} />)}</div>
                <section className="panel">
                    <h2 className="font-bold">Audience and traffic</h2>
                    <p className="mt-2 text-sm">{analytics.uniqueMembers} distinct signed-in viewers in this period. Guests are not counted as unique people.</p>
                    <p className="mt-1 text-sm">{analytics.guestSessions} guest browser sessions in this period. A guest may use more than one session; older guest views are excluded from this estimate.</p>
                    <p className="mt-1 text-xs text-ink-muted">Video plays count once per viewer per video per day. Comic reading progress is shown in the Comics section.</p>
                    <p className="mt-1 text-xs text-ink-muted">Sources are first referring pages for each daily work view; external hostnames and viewer identities are never exposed.</p>
                    <ul className="mt-3 space-y-2">{analytics.sources.map((source) => <li key={source.source} className="flex justify-between gap-2 text-sm capitalize"><span>{source.source}</span><span>{source.views} views</span></li>)}</ul>
                    {!analytics.sources.length && <p className="mt-2 text-sm text-ink-muted">No recorded traffic in this period.</p>}
                </section>
                <div className="grid gap-3 md:grid-cols-2">{Object.entries(analytics.breakdowns).map(([kind, rows]) => <section key={kind} className="panel min-w-0">
                    <h2 className="font-bold capitalize">What performs well: {kind}</h2>
                    <p className="mt-1 text-xs text-ink-muted">Likes on works in the selected period. Tags overlap; these totals should not be added together.</p>
                    {rows.map((row) => <div key={row.label} className="mt-2 flex flex-wrap justify-between gap-2 text-sm"><span className="break-words">{row.label}</span><span>{row.likes} likes · {row.works} works</span></div>)}
                    {!rows.length && <p className="mt-2 text-sm text-ink-muted">No data yet.</p>}
                </section>)}</div>
                <section className="panel">
                    <h2 className="font-bold">Plan posting times</h2>
                    <p className="mt-2 text-sm">{analytics.postingSuggestion
                        ? `Your sampled posts around ${String(analytics.postingSuggestion.hour).padStart(2, '0')}:00 ${analytics.timezone} averaged ${analytics.postingSuggestion.averageLikes} likes. Try this time as an experiment, not a guarantee.`
                        : 'Not enough evidence for a posting-time suggestion. We need at least 10 posts, 20 likes, and 3 posts in a matching hour in the selected period.'}</p>
                    <p className="mt-2 text-xs text-ink-muted">Observed likes by upload hour, not audience online times. Newer posts have had less time to collect likes.</p>
                    {analytics.postingHours.map((hour) => <p key={hour.hour} className="mt-1 text-sm">{String(hour.hour).padStart(2, '0')}:00 · {hour.posts} posts · {hour.averageLikes} average likes</p>)}
                    <Link href={route('studio.index', { ...filters, tab: 'planner' })} className="mt-3 inline-block text-ink-brand">Open planner</Link>
                </section>
                <h2 className="font-bold">Compare works</h2>
                <div className="grid gap-3 md:grid-cols-2">{performance.slice(0, 6).map((post) => <PerformanceCard key={post.id} post={post} filters={filters} />)}</div>
            </>}
            {filters.tab === 'comics' && <>
                <h2 className="text-lg font-bold">Comic readership and chapter planning</h2>
                <p className="text-xs text-ink-muted">Readership below is lifetime tracked browser sessions / member accounts since {comicTrackingSince}. Completion and last-page summaries use signed-in readers only. Completion requires a recorded final-page visit; older activity may be missing. Last reached page is not proof a reader abandoned the comic.</p>
                {comicAnalytics.map((comic) => <section key={comic.id} className="panel min-w-0 space-y-3">
                    <Link href={comic.url} className="break-words text-lg font-bold text-ink-brand">{comic.title}</Link>
                    <p className="text-sm text-ink-muted">{comic.status} · {comic.followers} series followers</p>
                    {comic.chapters.map((chapter) => <div key={chapter.id} className="rounded-lg border border-ink-line p-3">
                        <h3 className="break-words font-semibold">Chapter {chapter.number} {chapter.title} {chapter.draft && '· Private draft'}</h3>
                        <p className="mt-1 text-sm">{chapter.pages} pages · {chapter.views} chapter opens · {chapter.readers} tracked readers · {chapter.memberReaders} signed-in · {chapter.completed} completed · {chapter.completionRate ?? '—'}% member completion</p>
                        {chapter.dropOff.length > 0 && <details className="mt-2 text-sm"><summary className="cursor-pointer text-ink-brand">Where incomplete readers last reached</summary>{chapter.dropOff.map((row) => <p key={row.page}>Page {row.page}: {row.readers} readers</p>)}</details>}
                        {chapter.url && <Link href={chapter.url} className="mt-2 inline-block text-sm text-ink-brand">Open chapter</Link>}
                    </div>)}
                    <Link href={route('studio.index', { ...filters, tab: 'planner' })} className="inline-block min-h-11 py-2 text-ink-brand">Plan next chapter</Link>
                </section>)}
                {!comicAnalytics.length && <p className="text-sm text-ink-muted">Create a comic series to see readership here.</p>}
                <AdminPagination {...comicPages} />
            </>}
        </div>
    );
}
