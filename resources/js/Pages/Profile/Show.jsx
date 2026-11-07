import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout, { TopBar, RatingBadge } from '@/Layouts/AppLayout';
import { PostCard, SeriesCard, WorkCell } from '@/Components/Work';
import { Icon } from '@/Components/Icons';
import { compact } from '@/Lib/format';
import { useState } from 'react';

export default function ProfileShow({ profile, posts, comics, tab, isOwner, isFollowing }) {
    const { auth } = usePage().props;
    const [following, setFollowing] = useState(isFollowing);

    const tabs = [
        { key: 'works', label: 'Works' },
        { key: 'albums', label: 'Albums' },
        { key: 'comics', label: 'Comics' },
    ];

    const goTab = (key) => router.get(route('profile.show', profile.username), { tab: key }, { preserveScroll: true, preserveState: true });

    return (
        <AppLayout>
            <Head title={`${profile.name} (@${profile.username})`} />

            <div className="relative h-[140px] overflow-hidden bg-gradient-to-br from-[#3a2740] via-[#1d1a24] to-[#3b2a1c] md:h-[200px]">
                {profile.banner && <img src={profile.banner} alt="" className="h-full w-full object-cover" />}
                <div className="absolute inset-0 bg-[radial-gradient(600px_200px_at_20%_0%,rgba(232,176,75,0.22),transparent_70%)]" />
            </div>

            <div className="-mt-11 grid grid-cols-[auto_minmax(0,1fr)] items-end gap-2.5 px-3.5 pb-3.5 md:-mt-14 md:grid-cols-[auto_minmax(0,1fr)_auto] md:gap-3.5 md:px-5">
                <img
                    src={profile.avatar}
                    alt=""
                    className="h-[88px] w-[88px] rounded-full border-4 border-ink-bg bg-[#222] object-cover md:h-32 md:w-32"
                />
                <div className="min-w-0 pt-11 md:pt-14">
                    <h1 className="truncate text-[19px] font-extrabold tracking-[-0.02em] md:text-[22px]">{profile.name}</h1>
                    <p className="truncate text-sm text-ink-muted">@{profile.username}</p>
                </div>
                <div className="col-span-2 flex flex-wrap items-center gap-2 pt-1 md:col-span-1 md:pt-14">
                    {isOwner ? (
                        <>
                            <Link href={route('settings.edit')} className="btn-ghost">
                                Edit profile
                            </Link>
                            <Link href={route('upload.create')} className="btn-primary">
                                Upload
                            </Link>
                        </>
                    ) : (
                        auth.user && (
                            <>
                                <Link href={route('messages.index', { to: profile.username })} className="btn-ghost">
                                    Message
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setFollowing(!following);
                                        router[following ? 'delete' : 'post'](route(following ? 'users.unfollow' : 'users.follow', profile.username), {}, { preserveScroll: true });
                                    }}
                                    className={following ? 'btn-ghost' : 'btn-primary'}
                                >
                                    {following ? 'Following' : 'Follow'}
                                </button>
                            </>
                        )
                    )}
                </div>
            </div>

            {profile.bio && <p className="px-3.5 text-[14.5px] leading-relaxed text-[#dfd7e5] md:px-5">{profile.bio}</p>}

            <div className="flex flex-wrap gap-4 px-3.5 pt-2.5 text-[13.5px] text-ink-muted md:px-5">
                {profile.location && <span>📍 {profile.location}</span>}
                {profile.website && <span>🔗 {profile.website}</span>}
                <span>🗓 Joined {new Date(profile.joined).toLocaleDateString(undefined, { month: 'short', year: 'numeric' })}</span>
            </div>

            <div className="flex flex-wrap gap-4 px-3.5 pb-3.5 pt-1.5 text-[13.5px] text-ink-muted md:px-5">
                <span>
                    <b className="text-ink-text">{compact(profile.following)}</b> Following
                </span>
                <span>
                    <b className="text-ink-text">{compact(profile.followers)}</b> Followers
                </span>
                <span>
                    <b className="text-ink-text">{compact(profile.posts_count)}</b> works
                </span>
            </div>

            <div className="scrollbar-none sticky top-0 z-[13] flex overflow-x-auto border-b border-ink-line bg-ink-bg/90 backdrop-blur" role="tablist">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        role="tab"
                        aria-selected={tab === t.key}
                        onClick={() => goTab(t.key)}
                        className={`min-h-12 whitespace-nowrap border-b-2 px-4 text-sm font-semibold ${
                            tab === t.key ? 'border-ink-brand text-ink-text' : 'border-transparent text-ink-muted hover:text-ink-text'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {tab === 'comics' ? (
                comics.length === 0 ? (
                    <p className="px-5 py-16 text-center text-sm text-ink-muted">No series yet.</p>
                ) : (
                    <div className="grid grid-cols-2 gap-3 p-3.5 sm:grid-cols-3 md:p-5">
                        {comics.map((c) => (
                            <SeriesCard key={c.id} comic={{ ...c, status: 'ongoing' }} />
                        ))}
                    </div>
                )
            ) : posts.length === 0 ? (
                <p className="px-5 py-16 text-center text-sm text-ink-muted">Nothing here yet.</p>
            ) : (
                <div className="grid grid-cols-2 gap-1.5 p-1.5 md:grid-cols-3">
                    {posts.map((post) => (
                        <Link
                            key={post.id}
                            href={post.url}
                            className="group relative aspect-square overflow-hidden bg-[#1c1820]"
                        >
                            {post.media?.[0] && (
                                <img src={post.media[0].thumb || post.media[0].url} alt="" loading="lazy" className="h-full w-full object-cover" />
                            )}
                            <span className="absolute right-1.5 top-1.5">
                                <RatingBadge rating={post.rating} label={post.rating_label} />
                            </span>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
