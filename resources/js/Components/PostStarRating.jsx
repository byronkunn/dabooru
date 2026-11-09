import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Icon } from '@/Components/Icons';

export default function PostStarRating({ postId, rating }) {
    const { auth } = usePage().props;
    const [preview, setPreview] = useState(null);
    const [saved, setSaved] = useState(false);
    const form = useForm({ stars: rating.mine ?? 0 });

    // The server is the source of truth. Keep the local copy in step with the
    // props so the tally and "your rating" never show a stale optimistic value.
    useEffect(() => {
        form.setData('stars', rating.mine ?? 0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rating.mine, rating.average, rating.count]);

    const selected = preview ?? form.data.stars ?? 0;

    const rate = (stars) => {
        setSaved(false);
        form.transform(() => ({ stars }));
        form.put(route('posts.stars', postId), {
            preserveScroll: true,
            onSuccess: () => setSaved(true),
        });
    };

    return (
        <section className="mt-3 rounded-xl border border-ink-line bg-ink-surface p-2.5 md:p-3" aria-label="Post star rating">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-[13px] font-bold md:text-sm">Rate this work</h2>
                <p className="text-xs text-ink-muted">
                    {rating.count ? `${rating.average.toFixed(1)} / 5 · ${rating.count} rating${rating.count === 1 ? '' : 's'}` : 'No ratings yet'}
                </p>
            </div>
            <div className="mt-2 flex flex-wrap items-center gap-2">
                <div className="flex" role="group" aria-label="Choose one to five stars" onMouseLeave={() => setPreview(null)}>
                    {[1, 2, 3, 4, 5].map((stars) => (
                        <button key={stars} type="button"
                            aria-label={`Rate ${stars} star${stars === 1 ? '' : 's'}`}
                            aria-pressed={form.data.stars === stars}
                            disabled={!rating.canRate || form.processing}
                            onClick={() => rate(stars)}
                            onMouseEnter={() => { if (rating.canRate) setPreview(stars); }}
                            onFocus={() => setPreview(stars)}
                            onBlur={() => setPreview(null)}
                            className={`grid h-9 w-9 place-items-center rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-ink-brand disabled:cursor-default md:h-11 md:w-11 ${stars <= selected ? 'text-ink-brand' : 'text-ink-faint'}`}
                        >
                            <Icon name={stars <= selected ? 'starFilled' : 'star'} className="h-6 w-6 md:h-7 md:w-7" />
                        </button>
                    ))}
                </div>
                <p className="text-xs text-ink-muted" role="status">
                    {form.processing
                        ? 'Saving rating…'
                        : rating.mine
                            ? `Your rating: ${rating.mine} / 5.${saved ? ' Saved.' : ' Choose another star to change it.'}`
                            : 'Choose 1–5 stars.'}
                </p>
            </div>
            {form.errors.stars && <p role="alert" className="mt-2 text-xs text-ink-expl">{form.errors.stars}</p>}
            {!auth.user ? <Link href={route('login')} className="mt-2 inline-block text-xs font-semibold text-ink-brand hover:underline">Sign in to rate this work</Link>
                : !rating.canRate && <p className="mt-2 text-xs text-ink-muted">Rating is unavailable for this account.</p>}
        </section>
    );
}
