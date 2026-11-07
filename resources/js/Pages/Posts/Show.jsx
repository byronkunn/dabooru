import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout, { TopBar, RatingBadge } from '@/Layouts/AppLayout';
import { MediaViewer, WorkCell } from '@/Components/Work';
import { Icon } from '@/Components/Icons';
import { compact } from '@/Lib/format';
import { useState } from 'react';

export default function PostShow({ post, comments, more, liked, saved, followedAuthor }) {
    const { auth } = usePage().props;
    const [revealed, setRevealed] = useState(false);
    const [isLiked, setLiked] = useState(liked);
    const [isSaved, setSaved] = useState(saved);
    const [isFollowing, setFollowing] = useState(followedAuthor);
    const form = useForm({ body: '' });

    const blurred = post.rating === 'explicit' && !revealed;
    const isVideo = post.media?.[0]?.kind === 'video';

    const submitComment = (e) => {
        e.preventDefault();
        if (!form.data.body.trim()) return;
        form.post(route('comments.store', post.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AppLayout>
            <Head title={post.title ?? 'Work'} />
            <TopBar title="Work" back={route('gallery')} />

            <article className="mx-auto max-w-[720px] border-b border-ink-line px-4 pb-4 pt-4 md:px-5">
                <div className="flex items-center gap-2.5">
                    <Link href={route('profile.show', post.user.username)}>
                        <img src={post.user.avatar} alt="" className="h-11 w-11 rounded-full object-cover" />
                    </Link>
                    <div className="min-w-0">
                        <Link href={route('profile.show', post.user.username)} className="block truncate text-[15px] font-bold hover:underline">
                            {post.user.name}
                        </Link>
                        <p className="truncate text-[13px] text-ink-muted">
                            @{post.user.username} · {post.created_at_human}
                        </p>
                    </div>
                    <div className="ml-auto flex items-center gap-2">
                        <RatingBadge rating={post.rating} label={post.rating_label} />
                        {auth.user && auth.user.id !== post.user.id && (
                            <button
                                type="button"
                                onClick={() => {
                                    setFollowing(!isFollowing);
                                    router[isFollowing ? 'delete' : 'post'](route(isFollowing ? 'users.unfollow' : 'users.follow', post.user.username), {}, { preserveScroll: true });
                                }}
                                className={isFollowing ? 'btn-ghost h-9 px-3 text-[13px]' : 'btn-primary h-9 px-3 text-[13px]'}
                            >
                                {isFollowing ? 'Following' : 'Follow'}
                            </button>
                        )}
                    </div>
                </div>

                {post.title && <h2 className="mt-4 text-xl font-bold tracking-[-0.02em]">{post.title}</h2>}
                {post.caption && <p className="mt-2 whitespace-pre-wrap text-[15px] leading-relaxed text-[#dfd7e5]">{post.caption}</p>}

                {post.type === 'comic' && post.chapter && (
                    <div className="mt-4 flex flex-wrap items-center gap-2.5 rounded-2xl border border-ink-line bg-ink-raised p-4">
                        <img src={post.media?.[0]?.thumb} alt="" className="h-[132px] w-[99px] rounded-xl object-cover" />
                        <div className="min-w-0 flex-1">
                            <p className="text-[15px] font-bold">{post.chapter.comic.title}</p>
                            <p className="mt-1 text-[13px] text-ink-muted">
                                Chapter {post.chapter.number} · {post.chapter.pages} pages
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Link href={route('comics.show', post.chapter.comic.slug)} className="btn-ghost">
                                Series page
                            </Link>
                            <Link href={post.chapter.url} className="btn-primary">
                                Read chapter
                            </Link>
                        </div>
                    </div>
                )}

                {post.media?.length > 1 && (
                    <div className={`mt-4 grid gap-2 ${post.type === 'album' ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-2'}`}>
                        {post.media.map((m) => (
                            <MediaViewer key={m.id} media={m} blurred={false} className="overflow-hidden rounded-xl border border-ink-line" />
                        ))}
                    </div>
                )}

                {post.media?.length === 1 && (
                    <div className="mt-4 overflow-hidden rounded-2xl border border-ink-line bg-black">
                        <MediaViewer media={post.media[0]} blurred={blurred} onReveal={() => setRevealed(true)} />
                    </div>
                )}

                <div className="mt-4 flex items-center gap-5 border-b border-ink-line pb-4 text-[13.5px] text-ink-muted">
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

                <div className="mt-3 flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => {
                            setLiked(!isLiked);
                            router[isLiked ? 'delete' : 'post'](route(isLiked ? 'posts.unlike' : 'posts.like', post.id), {}, { preserveScroll: true });
                        }}
                        className={`btn-ghost gap-2 ${isLiked ? 'border-[#e8607d]/50 text-[#e8607d]' : ''}`}
                        aria-pressed={isLiked}
                    >
                        <Icon name="heart" className="h-4 w-4" /> {isLiked ? 'Liked' : 'Like'}
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setSaved(!isSaved);
                            router[isSaved ? 'delete' : 'post'](route(isSaved ? 'posts.unsave' : 'posts.save', post.id), {}, { preserveScroll: true });
                        }}
                        className={`btn-ghost gap-2 ${isSaved ? 'border-ink-brand/50 text-ink-brand' : ''}`}
                        aria-pressed={isSaved}
                    >
                        <Icon name="save" className="h-4 w-4" /> {isSaved ? 'Saved' : 'Save'}
                    </button>
                    <button
                        type="button"
                        onClick={() => navigator.clipboard?.writeText(window.location.href)}
                        className="btn-ghost gap-2"
                    >
                        <Icon name="share" className="h-4 w-4" /> Copy link
                    </button>
                </div>
            </article>

            {/* comments */}
            <section className="mx-auto max-w-[720px]" aria-label="Comments">
                {auth.user ? (
                    <form onSubmit={submitComment} className="flex items-start gap-2.5 border-b border-ink-line px-4 py-3.5 md:px-5">
                        <img src={auth.user.avatar} alt="" className="h-9 w-9 shrink-0 rounded-full object-cover" />
                        <textarea
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                            placeholder="Add a reply…"
                            rows={2}
                            maxLength={2000}
                            className="field-input min-h-[52px] resize-none border-0 bg-transparent px-0 text-[15px] focus:ring-0"
                        />
                        <button type="submit" disabled={form.processing || !form.data.body.trim()} className="btn-primary self-end">
                            Reply
                        </button>
                    </form>
                ) : (
                    <div className="flex items-center justify-between gap-3 border-b border-ink-line px-4 py-4 md:px-5">
                        <p className="text-sm text-ink-muted">Log in to reply.</p>
                        <Link href={route('login')} className="btn-primary">
                            Log in
                        </Link>
                    </div>
                )}

                {comments.length === 0 ? (
                    <p className="px-5 py-10 text-center text-sm text-ink-muted">No comments yet.</p>
                ) : (
                    comments.map((comment) => (
                        <div key={comment.id} className="grid grid-cols-[36px_minmax(0,1fr)] gap-2.5 border-b border-ink-line px-4 py-3.5 md:px-5">
                            <img src={comment.user.avatar} alt="" className="h-9 w-9 rounded-full object-cover" />
                            <div className="min-w-0">
                                <p className="flex flex-wrap items-center gap-1.5 text-[14px]">
                                    <Link href={route('profile.show', comment.user.username)} className="font-bold hover:underline">
                                        {comment.user.name}
                                    </Link>
                                    <span className="text-[13px] text-ink-muted">@{comment.user.username}</span>
                                    <span className="text-ink-muted">·</span>
                                    <span className="text-[13px] text-ink-muted">{comment.created_at_human}</span>
                                </p>
                                <p className="mt-1 whitespace-pre-wrap text-[14.5px] leading-snug">{comment.body}</p>
                            </div>
                        </div>
                    ))
                )}
            </section>

            {more.length > 0 && (
                <section className="mx-auto max-w-[720px] px-3 py-5 md:px-5">
                    <h2 className="mb-3 px-1 text-[15px] font-bold">More from {post.user.name}</h2>
                    <div className="grid grid-cols-2 gap-2 md:grid-cols-3">
                        {more.map((item) => (
                            <WorkCell key={item.id} post={item} wide />
                        ))}
                    </div>
                </section>
            )}
        </AppLayout>
    );
}
