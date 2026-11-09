import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';

/**
 * Shown once, right after registration, before the reader lands in the gallery.
 * Everything here is optional: Skip is always one tap away.
 */
export default function SetupIndex({ suggestedTags, suggestedArtists, showExplicit }) {
    const [step, setStep] = useState(0);
    const [tags, setTags] = useState([]);
    const [artists, setArtists] = useState([]);
    const [explicit, setExplicit] = useState(showExplicit);

    const form = useForm({ tags: [], artists: [], show_explicit: showExplicit });
    const total = 3;

    const toggle = (list, setList, value) =>
        setList(list.includes(value) ? list.filter((v) => v !== value) : [...list, value]);

    const finish = () => {
        form.transform(() => ({ tags, artists, show_explicit: explicit }));
        form.post(route('setup.store'), { preserveScroll: true });
    };

    const skip = () => router.post(route('setup.skip'));

    if (suggestedTags.length === 0 && suggestedArtists.length === 0) {
        // nothing to suggest yet (a very new site) — do not block the reader
        return (
            <AppLayout rightRail={false}>
                <Head title="Welcome" />
                <div className="mx-auto max-w-[560px] px-4 py-16 text-center">
                    <h1 className="text-xl font-bold">Welcome to ModernBooru</h1>
                    <p className="mt-2 text-[13.5px] text-ink-muted">
                        There is not much to choose from yet. Start browsing and your feed will build itself.
                    </p>
                    <button type="button" onClick={skip} className="btn-primary mt-5 w-full">
                        Start browsing
                    </button>
                </div>
            </AppLayout>
        );
    }

    const steps = [
        {
            title: 'Pick a few tags',
            help: 'Works with these tags show up in your feed first.',
            body: (
                <div className="flex flex-wrap gap-1.5">
                    {suggestedTags.map((tag) => {
                        const on = tags.includes(tag.name);
                        return (
                            <button
                                key={tag.id}
                                type="button"
                                aria-pressed={on}
                                onClick={() => toggle(tags, setTags, tag.name)}
                                className={`chip ${on ? 'is-active' : ''}`}
                            >
                                #{tag.name}
                                <span className="ml-1 opacity-60">{tag.works}</span>
                            </button>
                        );
                    })}
                </div>
            ),
        },
        {
            title: 'Follow some artists',
            help: 'Their new work appears in Following.',
            body: (
                <ul className="divide-y divide-ink-line">
                    {suggestedArtists.map((artist) => {
                        const on = artists.includes(artist.username);
                        return (
                            <li key={artist.username} className="flex items-center gap-3 py-2.5">
                                <img src={artist.avatar} alt="" className="h-10 w-10 shrink-0 rounded-full bg-ink-raised object-cover" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-[14px] font-bold">{artist.name}</p>
                                    <p className="truncate text-[12px] text-ink-muted">@{artist.username} · {artist.works} works</p>
                                </div>
                                <button
                                    type="button"
                                    aria-pressed={on}
                                    onClick={() => toggle(artists, setArtists, artist.username)}
                                    className={on ? 'btn-ghost min-h-[34px] text-[12.5px] text-ink-brand' : 'btn-primary min-h-[34px] text-[12.5px]'}
                                >
                                    {on ? 'Following' : 'Follow'}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            ),
        },
        {
            title: 'How much do you want to see?',
            help: 'You can change this any time in Settings.',
            body: (
                <button
                    type="button"
                    role="switch"
                    aria-checked={explicit}
                    onClick={() => setExplicit((v) => !v)}
                    className="flex w-full items-center justify-between gap-3 rounded-xl border border-ink-line bg-ink-surface px-3 py-3 text-left"
                >
                    <span>
                        <span className="block text-[14px] font-bold">Show explicit work</span>
                        <span className="block text-[12px] text-ink-muted">
                            {explicit ? 'Explicit work is visible, marked 18+.' : 'Explicit work stays hidden.'}
                        </span>
                    </span>
                    <span className={`relative h-6 w-11 shrink-0 rounded-full transition-colors ${explicit ? 'bg-ink-brand/45' : 'bg-[#3a3242]'}`}>
                        <span className={`absolute top-0.5 h-5 w-5 rounded-full transition-all ${explicit ? 'left-[22px] bg-ink-brand' : 'left-0.5 bg-[#8d8297]'}`} />
                    </span>
                </button>
            ),
        },
    ];

    const current = steps[step];
    const isLast = step === total - 1;

    return (
        <AppLayout rightRail={false}>
            <Head title="Set up your feed" />
            <div className="mx-auto max-w-[560px] px-4 pb-24 pt-6">
                <div className="flex items-center gap-3">
                    <div className="flex flex-1 gap-1" aria-hidden="true">
                        {steps.map((_, i) => (
                            <span key={i} className={`h-1 flex-1 rounded-full ${i <= step ? 'bg-ink-brand' : 'bg-[#3a3242]'}`} />
                        ))}
                    </div>
                    <button type="button" onClick={skip} className="shrink-0 text-[12.5px] font-semibold text-ink-muted hover:text-ink-text">
                        Skip
                    </button>
                </div>

                <p className="mt-5 text-[11.5px] font-bold uppercase tracking-[0.1em] text-ink-faint">
                    Step {step + 1} of {total}
                </p>
                <h1 className="mt-1 text-[20px] font-extrabold tracking-[-0.02em]">{current.title}</h1>
                <p className="mb-4 mt-1 text-[13px] text-ink-muted">{current.help}</p>

                {current.body}

                <div className="mt-6 flex items-center gap-2">
                    {step > 0 && (
                        <button type="button" onClick={() => setStep(step - 1)} className="btn-ghost min-h-11 px-4">
                            Back
                        </button>
                    )}
                    {isLast ? (
                        <button type="button" onClick={finish} disabled={form.processing} className="btn-primary min-h-11 flex-1">
                            {form.processing ? 'Setting up…' : 'Start browsing'}
                        </button>
                    ) : (
                        <button type="button" onClick={() => setStep(step + 1)} className="btn-primary min-h-11 flex-1">
                            Next
                        </button>
                    )}
                </div>

                <p className="mt-3 flex items-center justify-center gap-1.5 text-[12px] text-ink-faint">
                    <Icon name="check" className="h-3.5 w-3.5" />
                    {tags.length} tags · {artists.length} artists chosen
                </p>
            </div>
        </AppLayout>
    );
}
