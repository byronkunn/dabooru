import WorkLink from '@/Components/WorkLink';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout, { TopBar, RatingBadge } from '@/Layouts/AppLayout';
import { PostCard, SeriesCard, WorkCell } from '@/Components/Work';
import { CollectionCover } from '@/Components/CollectionView';
import { Icon } from '@/Components/Icons';
import SearchBox from '@/Components/SearchBox';
import ProfileConnectionsDialog from '@/Components/ProfileConnectionsDialog';
import ProfileImageDialog from '@/Components/ProfileImageDialog';
import ProfileActions from '@/Components/ProfileActions';
import { compact } from '@/Lib/format';
import { useState } from 'react';

export default function ProfileShow({ profile, posts, comics, collections = [], tab, isOwner, isFollowing, relationship, hasStickerPacks = false }) {
    const { auth } = usePage().props;
    const [connections, setConnections] = useState(null);
    const [editingImage, setEditingImage] = useState(null);

    const tabs = [
        { key: 'works', label: 'Works' },
        { key: 'albums', label: 'Albums' },
        { key: 'comics', label: 'Comics' },
        { key: 'credited', label: 'Credited works' },
        { key: 'collections', label: 'Collections' },
    ];

    const goTab = (key) => router.get(route('profile.show', profile.username), { tab: key }, { preserveScroll: true, preserveState: true });

    return (
        <AppLayout>
            <Head title={`${profile.name} (@${profile.username})`} />

            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2 border-b border-ink-line bg-ink-bg/90 px-3.5 py-2 backdrop-blur md:px-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,320px)_minmax(0,1fr)]">
                <div className="hidden lg:block" aria-hidden="true" />
                <div className="hidden w-[min(38vw,320px)] lg:block"><SearchBox /></div>
                <div className="flex items-center justify-end gap-2 lg:col-start-3">
                    {isOwner && (
                        <Link href={route('collections.index')} aria-label="Saved works" className="btn-icon-flat shrink-0">
                            <Icon name="save" />
                        </Link>
                    )}
                    {isOwner && (
                        <Link href={route('settings.edit')} aria-label="Settings" className="btn-icon-flat">
                            <Icon name="settings" />
                        </Link>
                    )}
                </div>
            </div>

            <div className="relative h-[140px] overflow-hidden bg-gradient-to-br from-[#3a2740] via-[#1d1a24] to-[#3b2a1c] md:h-[200px]">
                {profile.banner && <img src={profile.banner} alt="" className="h-full w-full object-cover" />}
                <div className="absolute inset-0 bg-[radial-gradient(600px_200px_at_20%_0%,rgba(232,176,75,0.22),transparent_70%)]" />
                {isOwner && (
                    <button type="button" onClick={() => setEditingImage('banner')} aria-label="Edit banner image" className="absolute left-1/2 top-1/2 grid h-11 w-11 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-black/60 text-white transition hover:bg-black/80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
                        <Icon name="camera" />
                    </button>
                )}
            </div>

            <div className="relative -mt-11 grid min-w-0 grid-cols-[auto_minmax(0,1fr)] items-end gap-3 px-3.5 pb-3.5 md:-mt-14 md:gap-3.5 md:px-5">
                <div className="relative h-[88px] w-[88px] md:h-32 md:w-32">
                    <img src={profile.avatar} alt="" className="h-full w-full rounded-full border-4 border-ink-bg bg-[#222] object-cover" />
                    {isOwner && (
                        <button type="button" onClick={() => setEditingImage('avatar')} aria-label="Edit profile photo" className="absolute inset-0 m-auto grid h-11 w-11 place-items-center rounded-full bg-black/60 text-white transition hover:bg-black/80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
                            <Icon name="camera" />
                        </button>
                    )}
                </div>
                <div className="min-w-0 pt-11 md:pt-14">
                    <h1 className="break-words text-[19px] font-extrabold leading-tight tracking-[-0.02em] md:text-[22px]">{profile.name}</h1>
                    <p className="mt-1 break-all text-sm text-ink-muted">@{profile.username}</p>
                </div>
                {!isOwner && auth.user && (
                    <ProfileActions key={profile.id} profile={profile} following={isFollowing} relationship={relationship} hasStickerPacks={hasStickerPacks} />
                )}
            </div>

            {profile.bio && <p className="px-3.5 text-[14.5px] leading-relaxed text-[#dfd7e5] md:px-5">{profile.bio}</p>}

            <div className="flex flex-wrap gap-4 px-3.5 pt-2.5 text-[13.5px] text-ink-muted md:px-5">
                {profile.location && <span>📍 {profile.location}</span>}
                {profile.website && <span>🔗 {profile.website}</span>}
                <span>🗓 Joined {new Date(profile.joined).toLocaleDateString(undefined, { month: 'short', year: 'numeric' })}</span>
            </div>

            <div className="flex flex-wrap gap-4 px-3.5 pb-3.5 pt-1.5 text-[13.5px] text-ink-muted md:px-5">
                <button type="button" onClick={() => setConnections('following')} className="hover:underline" aria-haspopup="dialog">
                    <b className="text-ink-text">{compact(profile.following)}</b> Following
                </button>
                <button type="button" onClick={() => setConnections('followers')} className="hover:underline" aria-haspopup="dialog">
                    <b className="text-ink-text">{compact(profile.followers)}</b> Followers
                </button>
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

            {tab === 'collections' ? (
                collections.length === 0 ? (
                    <p className="px-5 py-16 text-center text-sm text-ink-muted">No public collections yet.</p>
                ) : (
                    <nav aria-label="Public collections" className="grid gap-3 p-3.5 sm:grid-cols-2 xl:grid-cols-3 md:p-5">
                        {collections.map((collection) => (
                            <Link key={collection.id} href={collection.url} className="flex min-w-0 items-center gap-3 rounded-xl border border-ink-line p-3 hover:border-ink-brand">
                                <CollectionCover collection={collection} className="h-16 w-16" />
                                <div className="min-w-0">
                                    <h2 className="break-words font-bold">{collection.name}</h2>
                                    <p className="text-sm text-ink-muted">{collection.count} {collection.count === 1 ? 'work' : 'works'} · Public</p>
                                    {collection.description && <p className="mt-1 line-clamp-2 text-sm text-ink-muted">{collection.description}</p>}
                                </div>
                            </Link>
                        ))}
                    </nav>
                )
            ) : tab === 'comics' ? (
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
                        <WorkLink
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
                        </WorkLink>
                    ))}
                </div>
            )}
            {connections && (
                <ProfileConnectionsDialog key={`${profile.username}-${connections}`} username={profile.username} type={connections} onClose={() => setConnections(null)} />
            )}
            {isOwner && editingImage && (
                <ProfileImageDialog key={editingImage} type={editingImage} current={profile[editingImage]} onClose={() => setEditingImage(null)} />
            )}
        </AppLayout>
    );
}
