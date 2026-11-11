import { returnToWorkSource } from '@/Components/WorkLink';
import { rememberWorkEngagement } from '@/Lib/workEngagement';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout, { TopBar, RatingBadge } from '@/Layouts/AppLayout';
import { ArtistName, BlurGate, MediaViewer, WorkCell } from '@/Components/Work';
import { Icon } from '@/Components/Icons';
import { compact } from '@/Lib/format';
import { copyText } from '@/Lib/clipboard';
import { useEffect, useRef, useState } from 'react';
import ReportDialog from '@/Components/ReportDialog';
import PostStarRating from '@/Components/PostStarRating';
import FullscreenMedia from '@/Components/FullscreenMedia';
import PostTags from '@/Components/PostTags';
import AdSlot from '@/Components/AdSlot';
import UserFollowButton from '@/Components/UserFollowButton';
import { shouldBlurMedia } from '@/Lib/mediaVisibility';
import PostComments from '@/Components/PostComments';
import SaveWorkActions from '@/Components/SaveWorkActions';

export default function PostShow({ post, viewToken, comments, canComment, commentPagination, commentSort = 'newest', more, moreFromUploader = [], similar = [], forYou = [], liked, saved, followedAuthor, creditClaim, starRating, tagEditing }) {
    const { auth } = usePage().props;
    const [revealed, setRevealed] = useState(false);
    const [isLiked, setLiked] = useState(liked);
    const [reporting, setReporting] = useState(false);
    const [lightboxIndex, setLightboxIndex] = useState(null);
    const [copied, setCopied] = useState(false);
    const creditForm = useForm({ evidence: '' });
    const playSent = useRef(false);
    const blurred = shouldBlurMedia(post.rating, auth?.user?.show_explicit, revealed);

    useEffect(() => {
        playSent.current = false;
        if (!viewToken || blurred) return;
        let remaining = 2000;
        let started = 0;
        let timer;
        let sent = false;
        const visibility = () => {
            if (document.visibilityState === 'visible' && !sent) {
                started = performance.now();
                timer = setTimeout(() => {
                    sent = true;
                    window.axios.post(route('posts.track', post.id), { token: viewToken, event: 'view' }).catch(() => {});
                }, remaining);
            } else if (timer) {
                clearTimeout(timer);
                timer = null;
                remaining = Math.max(0, remaining - (performance.now() - started));
            }
        };
        document.addEventListener('visibilitychange', visibility);
        visibility();
        return () => { clearTimeout(timer); document.removeEventListener('visibilitychange', visibility); };
    }, [post.id, viewToken, blurred]);

    const trackPlay = () => {
        if (!viewToken || blurred || playSent.current) return;
        playSent.current = true;
        window.axios.post(route('posts.track', post.id), { token: viewToken, event: 'play' }).catch(() => {});
    };

    const isVideo = post.media?.[0]?.kind === 'video';

    const copyLink = async () => {
        if (await copyText(window.location.href)) {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    return (
        <AppLayout>
            <Head title={post.title ?? 'Work'} />
            <TopBar title="Work" back={route('gallery')} onBack={returnToWorkSource} />

            <article className="mx-auto max-w-[720px] border-b border-ink-line px-4 pb-4 pt-4 md:px-5">
                {post.type === 'comic' && post.chapter && (
                    <div className="flex flex-wrap items-center gap-2.5 rounded-2xl border border-ink-line bg-ink-raised p-4">
                        <img src={post.media?.[0]?.thumb} alt="" className={`h-[132px] w-[99px] rounded-xl object-cover ${blurred ? 'blur-2xl' : ''}`} />
                        <div className="min-w-0 flex-1">
                            <p className="text-[15px] font-bold">{post.chapter.comic.title}</p>
                            <p className="mt-1 text-[13px] text-ink-muted">
                                Chapter {post.chapter.number} · {post.chapter.pages} pages
                            </p>
                        </div>
                        <div className="flex w-full gap-2 sm:w-auto">
                            <Link href={route('comics.show', post.chapter.comic.slug)} className="btn-ghost flex-1 sm:flex-none">
                                Series page
                            </Link>
                            <Link href={post.chapter.url} className="btn-primary flex-1 sm:flex-none">
                                Read chapter
                            </Link>
                        </div>
                    </div>
                )}

                {post.media?.length > 1 && (
                    <div className={`grid gap-2 ${post.type === 'comic' ? 'mt-4' : ''} ${post.type === 'album' ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-2'}`}>
                        {post.media.map((m, index) => (
                            <div key={m.id} className="relative overflow-hidden rounded-xl border border-ink-line">
                            <button
                                type="button"
                                onClick={() => setLightboxIndex(index)}
                                className="group relative overflow-hidden rounded-xl border border-ink-line text-left focus:outline-none focus:ring-2 focus:ring-ink-brand"
                            >
                                <MediaViewer media={m} blurred={false} className={blurred ? 'blur-2xl' : ''} />
                                <span className="absolute bottom-1.5 right-1.5 rounded bg-black/70 px-1.5 py-0.5 text-[10.5px] font-bold text-white opacity-0 transition-opacity group-hover:opacity-100">
                                    Expand
                                </span>
                            </button>
                            {blurred && <BlurGate label="Explicit content" onReveal={() => setRevealed(true)} />}
                            </div>
                        ))}
                    </div>
                )}

                {post.media?.length === 1 && (
                    <div className={`relative overflow-hidden rounded-2xl border border-ink-line bg-black ${post.type === 'comic' ? 'mt-4' : ''}`}>
                        {post.media[0].kind === 'image' ? (
                            <button
                                type="button"
                                onClick={() => setLightboxIndex(0)}
                                className="block w-full text-left focus:outline-none"
                            >
                                <MediaViewer media={post.media[0]} blurred={false} className={blurred ? 'blur-2xl' : ''} />
                            </button>
                        ) : (
                            <MediaViewer media={post.media[0]} blurred={blurred} onReveal={() => setRevealed(true)} onPlay={trackPlay} />
                        )}
                        {blurred && post.media[0].kind === 'image' && <BlurGate label="Explicit content" onReveal={() => setRevealed(true)} />}
                    </div>
                )}

                <div className="mt-4 flex flex-wrap items-center gap-2.5">
                    {post.artist_name || post.external_artist ? (
                        <div className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-amber-500/20 text-amber-300">
                            <span className="text-base font-bold">✎</span>
                        </div>
                    ) : post.user ? (
                        <Link href={route('profile.show', post.user.username)}>
                            <img src={post.user.avatar} alt="" className="h-11 w-11 rounded-full bg-ink-raised object-cover" />
                        </Link>
                    ) : (
                        <span className="grid h-11 w-11 place-items-center rounded-full bg-ink-raised text-ink-faint">
                            <Icon name="user" className="h-5 w-5" />
                        </span>
                    )}
                    <div className="min-w-0">
                        {post.artist_name || post.external_artist ? (
                            <div>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <ArtistName post={post} className="block truncate text-[15px] font-bold" />
                                    <span className="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10.5px] font-bold text-amber-300">
                                        {post.credited_artist ? 'Verified artist credit' : 'Artist credit page'}
                                    </span>
                                </div>
                                <p className="truncate text-[12.5px] text-ink-muted">
                                    {post.user ? (
                                        <>
                                            Shared by{' '}
                                            <Link href={route('profile.show', post.user.username)} className="text-ink-brand hover:underline">
                                                @{post.user.username}
                                            </Link>{' '}
                                            ·{' '}
                                        </>
                                    ) : null}
                                    {post.created_at_human}
                                </p>
                            </div>
                        ) : post.user ? (
                            <>
                                <Link href={route('profile.show', post.user.username)} className="block truncate text-[15px] font-bold hover:underline">
                                    {post.user.name}
                                </Link>
                                <p className="truncate text-[13px] text-ink-muted">
                                    @{post.user.username} · {post.created_at_human}
                                </p>
                            </>
                        ) : (
                            <>
                                <span className="block truncate text-[15px] font-bold">Unsorted</span>
                                <p className="truncate text-[13px] text-ink-muted">
                                    Unattributed · {post.created_at_human}
                                </p>
                            </>
                        )}
                    </div>
                    {auth.user && post.user && auth.user.id !== post.user.id && (
                        <UserFollowButton username={post.user.username} following={followedAuthor} />
                    )}
                    <div className="ml-auto flex flex-wrap items-center justify-end gap-2">
                        <RatingBadge rating={post.rating} label={post.rating_label} />
                        {auth.user && post.user && auth.user.id === post.user.id && (
                            <>
                                <Link href={route('posts.edit', post.id)} className="btn-ghost h-9 px-3 text-[13px]">
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (window.confirm('Delete this work? This cannot be undone.')) {
                                            router.delete(route('posts.destroy', post.id));
                                        }
                                    }}
                                    className="btn-ghost h-9 border-[#6b2c3a] px-3 text-[13px] text-[#ff98a9]"
                                >
                                    Delete
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {(post.artist_name || post.external_artist) && (
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-3.5">
                        <div className="min-w-0">
                            <p className="text-xs font-bold uppercase tracking-wider text-amber-300">{post.credited_artist ? 'Artist credit' : 'External artist'}</p>
                            <p className="mt-0.5 text-[14px] text-ink-text">
                                Created by <ArtistName post={post} className="link-brand font-bold" />.
                                {post.credited_artist && post.artist_name && <span className="ml-1 text-ink-muted">Originally credited as {post.artist_name}.</span>}
                            </p>
                            {!post.credited_artist && <p className="mt-1 text-xs text-ink-muted">Artist credit, not an account. Shared by a community member.</p>}
                        </div>
                        {(post.source_url || post.external_artist?.url) && (
                            <a
                                href={post.source_url || post.external_artist.url}
                                target="_blank"
                                rel="noreferrer"
                                className="btn-ghost shrink-0 border-amber-500/40 text-amber-300 hover:bg-amber-500/10"
                            >
                                Original source ↗
                            </a>
                        )}
                    </div>
                )}

                {creditClaim && (
                    <div className="mt-3 text-sm">
                        {creditClaim.status === 'pending' ? <p className="text-ink-muted">Your artist credit claim is awaiting review.</p>
                            : creditClaim.status !== 'revoked' && (
                                <details>
                                    <summary className="cursor-pointer font-semibold text-ink-brand">Claim artist credit</summary>
                                    <form className="mt-2 space-y-2" onSubmit={(event) => {
                                        event.preventDefault();
                                        creditForm.post(route('credits.store', post.id), { preserveScroll: true, onSuccess: () => creditForm.reset() });
                                    }}>
                                        <label className="block">Tell staff how they can verify this work is yours
                                            <textarea className="field-input mt-1" value={creditForm.data.evidence} onChange={(event) => creditForm.setData('evidence', event.target.value)} placeholder="A portfolio link or the original upload is helpful." rows={3} minLength={10} maxLength={2000} required />
                                        </label>
                                        {creditForm.errors.evidence && <p role="alert" className="text-ink-expl">{creditForm.errors.evidence}</p>}
                                        <button className="btn-ghost" disabled={creditForm.processing}>Submit claim</button>
                                    </form>
                                </details>
                            )}
                    </div>
                )}

                {post.title && <h2 className="mt-4 text-xl font-bold tracking-[-0.02em]">{post.title}</h2>}
                {post.caption && <p className="mt-2 whitespace-pre-wrap text-[15px] leading-relaxed text-[#dfd7e5]">{post.caption}</p>}
                {post.ai_tool && <p className="mt-2 text-sm text-ink-muted">AI tool: <span className="font-semibold text-ink-text">{post.ai_tool}</span>{post.ai_tool_type && ` · ${post.ai_tool_type === 'local' ? 'Local tool' : 'Online service'}`}</p>}

                <PostTags key={`tags-${post.id}`} post={post} editing={tagEditing} />

                <div className="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-ink-line pb-4 text-[13.5px] text-ink-muted">
                    <span>
                        <b className="text-ink-text">{compact(post.views)}</b> views
                    </span>
                    <span>
                        <b className="text-ink-text">{compact(post.likes)}</b> likes
                    </span>
                    <span>
                        <b className="text-ink-text">{compact(post.comments)}</b> comments
                    </span>
                    <span className="ml-auto text-xs">{post.rating === 'explicit' ? '18+ · visible with explicit on' : 'All ages'}</span>
                </div>

                <div className="mt-2.5 flex flex-wrap items-center gap-1.5 sm:gap-2">
                    <button
                        type="button"
                        onClick={() => {
                            setLiked(!isLiked);
                            router[isLiked ? 'delete' : 'post'](route(isLiked ? 'posts.unlike' : 'posts.like', post.id), {}, {
                                preserveScroll: true,
                                onSuccess: () => auth.user && rememberWorkEngagement(auth.user.id, post.id, 'liked', !isLiked),
                                onError: () => setLiked(isLiked),
                            });
                        }}
                        className={`btn-ghost gap-2 ${isLiked ? 'border-[#e8607d]/50 text-[#e8607d]' : ''}`}
                        aria-pressed={isLiked}
                    >
                        <Icon name="heart" className="h-4 w-4" /> {isLiked ? 'Liked' : 'Like'}
                    </button>
                    <SaveWorkActions postId={post.id} saved={saved} />
                    <button
                        type="button"
                        onClick={copyLink}
                        className="btn-ghost gap-2"
                    >
                        <Icon name="share" className="h-4 w-4" /> {copied ? 'Copied!' : 'Copy link'}
                    </button>
                    {auth.user && (
                        <button
                            type="button"
                            onClick={() => setReporting(true)}
                            className="btn-ghost gap-2"
                            title="Report this work to a moderator"
                        >
                            <Icon name="flag" className="h-4 w-4" /> Report
                        </button>
                    )}
                    {auth.user && post.user && auth.user.id !== post.user.id && (
                        <button
                            type="button"
                            onClick={() => {
                                if (window.confirm(`Block @${post.user.username}? Their work disappears from your feed.`)) {
                                    router.post(route('users.block', post.user.username));
                                }
                            }}
                            className="btn-ghost gap-2"
                        >
                            Block
                        </button>
                    )}
                </div>
                <PostStarRating key={post.id} postId={post.id} rating={starRating} />

            </article>
            <AdSlot className="mx-4 my-3 max-w-[720px] xl:hidden" placement="post-mobile" compact />

            <PostComments key={`${post.id}-${auth.user?.id}`} post={post} comments={comments} canComment={canComment} pagination={commentPagination} sort={commentSort} />

            {reporting && <ReportDialog type="post" id={post.id} onClose={() => setReporting(false)} />}

            {more.length > 0 && (
                <section className="mx-auto max-w-[720px] px-3 py-5 md:px-5">
                    <h2 className="mb-3 px-1 text-[15px] font-bold">
                        {post.artist_name ? `More credited to ${post.artist_name}` : post.user ? `More from ${post.user.name}` : 'More from the library'}
                    </h2>
                    <div className="grid grid-cols-2 gap-2 md:grid-cols-3">
                        {more.map((item) => (
                            <WorkCell key={item.id} post={item} wide />
                        ))}
                    </div>
                </section>
            )}

            {[
                { title: `More uploaded by ${post.user?.name ?? 'this user'}`, items: moreFromUploader },
                { title: 'Similar artwork', items: similar },
                { title: 'You might like', items: forYou, description: 'Based on artists and tags you follow' },
            ].filter((section) => section.items.length > 0).map((section) => (
                <section key={section.title} className="mx-auto max-w-[720px] px-3 py-5 md:px-5">
                    <h2 className="px-1 text-[15px] font-bold">{section.title}</h2>
                    {section.description && <p className="mt-1 px-1 text-xs text-ink-muted">{section.description}</p>}
                    <div className="mt-3 grid grid-cols-2 gap-2 md:grid-cols-3">
                        {section.items.map((item) => <WorkCell key={item.id} post={item} wide />)}
                    </div>
                </section>
            ))}

            {lightboxIndex !== null && post.media?.[lightboxIndex] && (
                <FullscreenMedia
                    items={post.media}
                    index={lightboxIndex}
                    onNavigate={setLightboxIndex}
                    onClose={() => setLightboxIndex(null)}
                    label={post.title ? `${post.title} media` : 'Work media'}
                    headerStart={post.title}
                    mediaClassName={blurred ? 'blur-2xl' : ''}
                />
            )}
        </AppLayout>
    );
}
