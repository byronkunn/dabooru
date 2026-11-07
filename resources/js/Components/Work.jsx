import { Link, router } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import { Icon } from '@/Components/Icons';
import { KIND_ICON, KIND_LABEL, RATING_CLASS, compact } from '@/Lib/format';

/* ------------------------------------------------------------------ media */
export function MediaViewer({ media, blurred, onReveal, className = '' }) {
    if (!media) return null;

    if (media.kind === 'video') {
        return (
            <div className={`relative overflow-hidden ${className}`}>
                <video
                    src={media.url}
                    controls
                    playsInline
                    preload="metadata"
                    className={`max-h-[70vh] w-full bg-black object-contain ${blurred ? 'blur-2xl' : ''}`}
                    onError={(e) => {
                        // some sample clips are h264 profiles the browser can't
                        // decode; show a poster-style placeholder instead of a broken box
                        const wrap = e.currentTarget.parentElement;
                        e.currentTarget.remove();
                        const note = document.createElement('div');
                        note.className = 'grid aspect-video place-items-center bg-ink-raised text-xs text-ink-muted';
                        note.textContent = 'Video preview unavailable in this browser';
                        wrap.appendChild(note);
                    }}
                />
                {blurred && <BlurGate label="Explicit video" onReveal={onReveal} />}
            </div>
        );
    }

    // A GIF only animates while it is the thing being looked at: on hover in
    // grids, and once tapped/clicked on a post. Nothing animates on its own.
    if (media.kind === 'gif') {
        return <GifViewer media={media} blurred={blurred} onReveal={onReveal} className={className} />;
    }

    return (
        <div className={`relative overflow-hidden ${className}`}>
            <img
                src={media.url}
                alt=""
                loading="lazy"
                width={media.width || undefined}
                height={media.height || undefined}
                className={`w-full bg-ink-raised object-cover ${blurred ? 'blur-2xl' : ''}`}
                style={media.width && media.height ? { aspectRatio: media.width / media.height } : undefined}
            />
            {blurred && <BlurGate label="Explicit content" onReveal={onReveal} />}
        </div>
    );
}

export function BlurGate({ label, onReveal }) {
    return (
        <div className="absolute inset-0 grid place-items-center bg-black/30 p-4 text-center">
            <div className="flex flex-col items-center gap-2">
                <span className="text-[13px] font-semibold text-[#e7dfea]">{label}</span>
                <span className="text-xs text-ink-muted">Hidden by your feed settings</span>
                <button type="button" onClick={onReveal} className="rounded-full border border-white/20 bg-white/10 px-3.5 py-2 text-[12.5px] font-bold backdrop-blur">
                    Show
                </button>
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ gallery cell */
export function WorkCell({ post, wide = false }) {
    const [revealed, setRevealed] = useState(false);
    const cover = post.media?.[0];
    const blurred = post.rating === 'explicit' && !revealed;
    const isPlayable = post.type === 'gif' || post.type === 'video';

    return (
        <Link
            href={post.url}
            className="group mb-3.5 block break-inside-avoid overflow-hidden rounded-card border border-ink-line bg-ink-surface hover-lift focus:outline-none focus-visible:ring-2 focus-visible:ring-ink-brand/60 no-tap-highlight"
            aria-label={`${post.title ?? 'Untitled'}${post.user?.name ? ' by ' + post.user.name : ''}`}
        >
            <div className="relative overflow-hidden bg-[#1c1820]" style={{ aspectRatio: cover?.aspect_ratio || 1 }}>
                {cover && (
                    <img
                        src={cover.thumb || cover.url}
                        alt=""
                        loading="lazy"
                        decoding="async"
                        className={`h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03] ${blurred ? 'blur-2xl' : ''}`}
                    />
                )}
                {blurred && (
                    <div className="absolute inset-0 grid place-items-center bg-black/25 text-center">
                        <span className="text-[11px] font-extrabold uppercase tracking-widest text-white/80">Explicit</span>
                    </div>
                )}
                <div className="absolute right-2 top-2 z-10 flex gap-1.5">
                    <span className={`${RATING_CLASS[post.rating]} shadow-[0_1px_6px_rgba(0,0,0,0.6)]`}>{post.rating_label}</span>
                </div>
                {isPlayable && (
                    <div className="pointer-events-none absolute inset-0 grid place-items-center">
                        <span className="grid h-11 w-11 place-items-center rounded-full bg-black/45 backdrop-blur">
                            <Icon name="play" className="h-5 w-5 translate-x-[1px] text-white" />
                        </span>
                    </div>
                )}
                {post.type === 'comic' && (
                    <span className="absolute bottom-2 left-2 z-10 kind-badge">
                        <Icon name="bookmark" className="h-3 w-3" /> Comic series
                    </span>
                )}
                <span className="absolute bottom-2 right-2 z-10 kind-badge">
                    {KIND_ICON[post.type]} {KIND_LABEL[post.type]}
                </span>
                {post.type === 'album' && (
                    <span className="absolute left-2 top-2 grid h-6 min-w-6 place-items-center rounded-md border border-white/15 bg-black/60 px-1.5 text-[11.5px] font-extrabold backdrop-blur">
                        {post.media.length}
                    </span>
                )}
            </div>

            <div className="flex flex-col gap-1.5 p-2.5">
                {post.artist_name || post.external_artist ? (
                    <div className="flex min-w-0 flex-col">
                        <div className="flex items-center gap-1.5">
                            <span className="truncate text-[13px] font-bold text-ink-text">
                                {post.artist_name || post.external_artist.name}
                            </span>
                            <span className="shrink-0 rounded bg-amber-500/15 px-1 py-0.5 text-[10px] font-bold text-amber-300">
                                External
                            </span>
                        </div>
                        {post.user && (
                            <span className="truncate text-[11px] text-ink-muted">
                                via @{post.user.username}
                            </span>
                        )}
                    </div>
                ) : (
                    <div className="flex min-w-0 items-center gap-2">
                        {post.user ? (
                            <img
                                src={post.user.avatar}
                                alt=""
                                className="h-[22px] w-[22px] shrink-0 rounded-full bg-ink-raised object-cover"
                            />
                        ) : (
                            <span className="grid h-[22px] w-[22px] shrink-0 place-items-center rounded-full bg-ink-raised text-ink-faint">
                                <Icon name="user" className="h-3 w-3" />
                            </span>
                        )}
                        <span className="truncate text-[13px] font-semibold">{post.user?.name ?? 'Unsorted'}</span>
                        {post.user && <span className="hidden truncate text-[13px] text-ink-muted sm:inline">@{post.user.username}</span>}
                    </div>
                )}
                {!wide && post.title && <p className="line-clamp-2 text-[13px] leading-snug text-[#d8d0dd]">{post.title}</p>}
                <div className="flex items-center gap-3.5 text-xs text-ink-muted">
                    <span className="inline-flex items-center gap-1">
                        <Icon name="heart" className="h-3.5 w-3.5" /> {compact(post.likes)}
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <Icon name="eye" className="h-3.5 w-3.5" /> {compact(post.views)}
                    </span>
                </div>
            </div>
        </Link>
    );
}

/* ------------------------------------------------------------------ feed card */
export function PostCard({ post, liked = false, saved = false }) {
    const [isLiked, setLiked] = useState(liked);
    const [isSaved, setSaved] = useState(saved);
    const [revealed, setRevealed] = useState(false);
    const cover = post.media?.[0];
    const blurred = post.rating === 'explicit' && !revealed;

    const toggle = (kind) => {
        const url = kind === 'like' ? `/p/${post.id}/like` : `/p/${post.id}/save`;
        const active = kind === 'like' ? isLiked : isSaved;

        if (kind === 'like') setLiked(!active);
        else setSaved(!active);

        router[active ? 'delete' : 'post'](url, {}, { preserveScroll: true, preserveState: true });
    };

    return (
        <article className="grid grid-cols-[40px_minmax(0,1fr)] gap-2.5 border-b border-ink-line px-3.5 py-3.5 hover:bg-white/[0.015] md:grid-cols-[48px_minmax(0,1fr)] md:gap-3 md:px-5 md:py-4">
            {post.user ? (
                <Link href={route('profile.show', post.user.username)}>
                    <img src={post.user.avatar} alt="" className="h-10 w-10 rounded-full bg-ink-raised object-cover md:h-12 md:w-12" />
                </Link>
            ) : (
                <span className="grid h-10 w-10 place-items-center rounded-full bg-ink-raised text-ink-faint md:h-12 md:w-12">
                    <Icon name="user" className="h-5 w-5" />
                </span>
            )}
            <div className="min-w-0">
                <div className="flex min-w-0 flex-wrap items-center gap-1.5 text-[14.5px]">
                    {post.artist_name || post.external_artist ? (
                        <div className="flex flex-wrap items-center gap-1.5">
                            <span className="font-bold text-ink-text">{post.artist_name || post.external_artist.name}</span>
                            <span className="rounded bg-amber-500/15 px-1.5 py-0.5 text-[10.5px] font-bold text-amber-300">
                                External artist
                            </span>
                            {(post.source_url || post.external_artist?.url) && (
                                <a
                                    href={post.source_url || post.external_artist.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-xs font-semibold text-ink-brand hover:underline"
                                >
                                    Source ↗
                                </a>
                            )}
                            {post.user && (
                                <span className="text-[12.5px] text-ink-muted">
                                    · shared by{' '}
                                    <Link href={route('profile.show', post.user.username)} className="hover:underline">
                                        @{post.user.username}
                                    </Link>
                                </span>
                            )}
                        </div>
                    ) : post.user ? (
                        <Link href={route('profile.show', post.user.username)} className="font-bold hover:underline">
                            {post.user.name}
                        </Link>
                    ) : (
                        <span className="font-bold">Unsorted</span>
                    )}
                    {!post.artist_name && !post.external_artist && post.user && (
                        <span className="text-[13.5px] font-medium text-ink-muted">@{post.user.username}</span>
                    )}
                    <span className="text-ink-muted">·</span>
                    <time className="text-[13.5px] text-ink-muted" dateTime={post.created_at}>
                        {post.created_at_human}
                    </time>
                    <span className="ml-auto flex items-center gap-1.5">
                        <span className={RATING_CLASS[post.rating]}>{post.rating_label}</span>
                        <button type="button" className="btn-icon-flat h-8 w-8" aria-label="More options">
                            <Icon name="more" className="h-4 w-4" />
                        </button>
                    </span>
                </div>

                <div className="mt-2">
                    {post.caption && <p className="mb-2.5 whitespace-pre-wrap text-[15px] leading-snug">{post.caption}</p>}

                    {post.type === 'comic' && post.chapter ? (
                        <Link href={post.chapter.url} className="flex items-center gap-3.5 rounded-2xl border border-ink-line bg-ink-raised p-3.5 hover:border-[#4a3c58]">
                            {cover && <img src={cover.thumb || cover.url} alt="" loading="lazy" className="h-[146px] w-[110px] shrink-0 rounded-xl object-cover" />}
                            <div className="min-w-0">
                                <p className="text-[15px] font-bold">
                                    {post.chapter.comic.title} · Ch. {post.chapter.number}
                                </p>
                                <p className="mt-1 text-[13px] leading-relaxed text-ink-muted">
                                    {post.chapter.pages} pages · {post.rating_label}
                                </p>
                                <span className="mt-2.5 inline-block rounded-[9px] bg-ink-brand px-3 py-2 text-[13px] font-extrabold text-[#1c1017]">
                                    Read chapter
                                </span>
                            </div>
                        </Link>
                    ) : post.media?.length > 1 ? (
                        <div className="grid grid-cols-2 gap-1 overflow-hidden rounded-2xl border border-ink-line">
                            {post.media.slice(0, 4).map((m) => (
                                <Link key={m.id} href={post.url} className="relative aspect-square bg-black">
                                    <img src={m.thumb || m.url} alt="" loading="lazy" className={`h-full w-full object-cover ${blurred ? 'blur-2xl' : ''}`} />
                                </Link>
                            ))}
                        </div>
                    ) : cover ? (
                        <Link href={post.url} className="relative block max-h-[520px] overflow-hidden rounded-2xl border border-ink-line bg-black">
                            <MediaViewer media={cover} blurred={blurred} onReveal={() => setRevealed(true)} className="max-h-[520px]" />
                            {post.type === 'album' && (
                                <span className="absolute bottom-2.5 right-2.5 rounded-full border border-white/15 bg-black/70 px-2.5 py-1 text-xs font-bold backdrop-blur">
                                    {post.media.length} pieces
                                </span>
                            )}
                        </Link>
                    ) : null}
                </div>

                <div className="mt-3 flex max-w-[460px] items-center justify-between text-[13px] text-ink-muted">
                    <button type="button" onClick={() => toggle('like')} className={`flex min-h-9 items-center gap-1.5 rounded-full px-2 hover:bg-white/5 hover:text-ink-text ${isLiked ? 'text-[#e8607d]' : ''}`} aria-pressed={isLiked}>
                        <Icon name="heart" className="h-[18px] w-[18px]" /> {compact(post.likes)}
                    </button>
                    <Link href={post.url} className="flex min-h-9 items-center gap-1.5 rounded-full px-2 hover:bg-white/5 hover:text-ink-text">
                        <Icon name="comment" className="h-[18px] w-[18px]" /> {compact(post.comments)}
                    </Link>
                    <button type="button" onClick={() => toggle('save')} className={`flex min-h-9 items-center gap-1.5 rounded-full px-2 hover:bg-white/5 hover:text-ink-text ${isSaved ? 'text-ink-brand' : ''}`} aria-pressed={isSaved}>
                        <Icon name="save" className="h-[18px] w-[18px]" /> Save
                    </button>
                    <button
                        type="button"
                        onClick={() => navigator.clipboard?.writeText(window.location.origin + post.url)}
                        className="flex min-h-9 items-center gap-1.5 rounded-full px-2 hover:bg-white/5 hover:text-ink-text"
                        aria-label="Copy link"
                    >
                        <Icon name="share" className="h-[18px] w-[18px]" />
                    </button>
                </div>
            </div>
        </article>
    );
}

/* ------------------------------------------------------------------ series card */
export function SeriesCard({ comic }) {
    return (
        <Link href={comic.url} className="group overflow-hidden rounded-card border border-ink-line bg-ink-surface hover-lift">
            <div className="relative aspect-[3/4] bg-[#211d26]">
                {comic.cover && <img src={comic.cover} alt="" loading="lazy" className="h-full w-full object-cover" />}
                <span className="absolute right-2 top-2">
                    <span className={RATING_CLASS[comic.rating]}>{comic.rating === 'suggestive' ? 'Suggestive' : comic.rating.toUpperCase()}</span>
                </span>
            </div>
            <div className="p-3">
                <p className="truncate text-[14px] font-bold">{comic.title}</p>
                <p className="mt-0.5 truncate text-[12.5px] text-ink-muted">
                    {comic.chapters} chapter{comic.chapters === 1 ? '' : 's'} · {comic.status}
                </p>
            </div>
        </Link>
    );
}

/* ------------------------------------------------------------------ masonry */
export function Masonry({ posts, renderAfterPost }) {
    return (
        <div className="columns-2 gap-2.5 md:columns-3 md:gap-3.5 xl:columns-3">
            {posts.map((post, index) => (
                <Fragment key={post.id}>
                    <WorkCell post={post} />
                    {renderAfterPost?.(post, index)}
                </Fragment>
            ))}
        </div>
    );
}

export function GifViewer({ media, blurred, onReveal, className = '' }) {
    const [playing, setPlaying] = useState(false);

    return (
        <div
            className={`relative overflow-hidden ${className}`}
            onMouseEnter={() => setPlaying(true)}
            onMouseLeave={() => setPlaying(false)}
        >
            <img
                src={media.url}
                alt=""
                loading="lazy"
                width={media.width || undefined}
                height={media.height || undefined}
                className={`w-full bg-ink-raised object-cover ${blurred ? 'blur-2xl' : ''}`}
            />
            <div className={`absolute inset-0 grid place-items-center transition-opacity ${playing ? 'opacity-0' : 'opacity-100'}`}>
                <span className="rounded-full border border-white/20 bg-black/55 px-3 py-1.5 text-[11.5px] font-bold text-white backdrop-blur">
                    {blurred ? 'Explicit GIF' : 'GIF · hover to play'}
                </span>
            </div>
            {blurred && <BlurGate label="Explicit content" onReveal={onReveal} />}
        </div>
    );
}
