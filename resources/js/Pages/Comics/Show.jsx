import { Head, Link, router } from '@inertiajs/react';
import AppLayout, { TopBar, RatingBadge } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';

export default function ComicShow({ comic }) {
    const toggleFollow = () => router.post(route('comics.follow', comic.id), {}, { preserveScroll: true });

    return (
        <AppLayout>
            <Head title={comic.title} />
            <TopBar
                title={comic.title}
                subtitle={`${comic.status} · ${comic.chapters.length} chapter${comic.chapters.length === 1 ? '' : 's'} · ${comic.rating_label}`}
                back={route('comics.index')}
                actions={
                    comic.continue && (
                        <Link href={comic.continue.url} className="btn-primary hidden h-9 px-3 text-[13px] md:inline-flex">
                            Continue Ch. {comic.continue.chapter}
                        </Link>
                    )
                }
            />

            <div className="grid gap-3.5 p-3.5 md:grid-cols-[240px_minmax(0,1fr)] md:gap-6 md:p-5">
                <img src={comic.cover} alt={`Cover: ${comic.title}`} className="h-auto w-[120px] rounded-xl border border-ink-line object-cover md:w-full" style={{ aspectRatio: '3/4' }} />
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <RatingBadge rating={comic.rating} label={comic.rating_label} />
                        <span className="text-[13px] text-ink-muted">
                            {comic.rating === 'explicit'
                                ? '18+ · some chapters contain adult content'
                                : comic.rating === 'suggestive'
                                    ? 'Some chapters are suggestive'
                                    : 'All chapters are safe for work'}
                        </span>
                    </div>
                    <h1 className="mt-2 text-[19px] font-bold tracking-[-0.02em] md:text-2xl">{comic.title}</h1>
                    <p className="mt-1 text-[13.5px] text-ink-muted">
                        by{' '}
                        <Link href={route('profile.show', comic.author.username)} className="font-bold text-ink-brand hover:underline">
                            {comic.author.name}
                        </Link>{' '}
                        · {comic.status}
                    </p>
                    {comic.description && <p className="mt-3 text-sm leading-relaxed text-[#cbbfd3]">{comic.description}</p>}

                    <div className="mt-4 flex flex-wrap gap-2">
                        {comic.continue && (
                            <Link href={comic.continue.url} className="btn-primary">
                                <Icon name="play" className="h-4 w-4" /> Continue Ch. {comic.continue.chapter}
                            </Link>
                        )}
                        <button type="button" onClick={toggleFollow} className={comic.followedSeries ? 'btn-ghost' : 'btn-ghost'} aria-pressed={comic.followedSeries}>
                            ★ {comic.followedSeries ? 'Following series' : 'Follow series'}
                        </button>
                    </div>
                </div>
            </div>

            <section className="mx-3.5 mt-2 border-t border-ink-line pt-4 md:mx-5" aria-label="Chapters">
                <h2 className="mb-1 flex items-center gap-2.5 text-base font-bold">
                    Chapters <span className="text-[13px] font-medium text-ink-muted">{comic.chapters.length}</span>
                </h2>
                {comic.chapters.map((ch) => (
                    <Link
                        key={ch.id}
                        href={ch.url}
                        className="flex min-h-[52px] items-center gap-3 rounded-xl px-2.5 py-3 text-sm hover:bg-ink-raised"
                    >
                        <span className={`w-12 shrink-0 text-[13px] font-extrabold ${ch.read ? 'text-ink-faint' : 'text-ink-brand'}`}>Ch. {ch.number}</span>
                        <span className="min-w-0 flex-1 truncate">{ch.title || `Chapter ${ch.number}`}</span>
                        {ch.read && <span className="shrink-0 text-xs text-ink-sfw">✓ read</span>}
                        <span className="shrink-0 text-[12.5px] text-ink-muted">{ch.published}</span>
                    </Link>
                ))}
            </section>
        </AppLayout>
    );
}
