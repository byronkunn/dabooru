import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Masonry } from '@/Components/Work';
import { useState } from 'react';

export default function GalleryIndex({ posts, hasMore, filters, stats, sortOptions, windowOptions, formatOptions }) {
    const { auth } = usePage().props;
    const [busy, setBusy] = useState(false);

    const navigate = (patch) => {
        const next = { ...filters, ...patch };
        router.get(route('gallery'), next, { preserveScroll: true, preserveState: true, replace: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    };

    const tabs = (
        <>
            <div className="scrollbar-none mt-3.5 flex gap-1.5 overflow-x-auto px-4 pb-2.5 md:px-5" role="tablist" aria-label="Format">
                {formatOptions.map((opt) => (
                    <button
                        key={opt.value}
                        type="button"
                        role="tab"
                        aria-selected={filters.format === opt.value}
                        onClick={() => navigate({ format: opt.value })}
                        className="chip"
                    >
                        {opt.label}
                    </button>
                ))}
            </div>

            <div className="scrollbar-none flex items-center gap-2 overflow-x-auto px-4 pb-3 md:px-5" role="group" aria-label="Sort and filters">
                <div className="flex shrink-0 rounded-full border border-ink-line bg-ink-raised p-[3px]">
                    {sortOptions.map((opt) => (
                        <button
                            key={opt.value}
                            type="button"
                            aria-pressed={filters.sort === opt.value}
                            onClick={() => navigate({ sort: opt.value })}
                            className={`min-h-9 whitespace-nowrap rounded-full px-3 text-[13px] font-semibold transition-colors ${
                                filters.sort === opt.value ? 'bg-ink-brand text-[#1c1017]' : 'text-ink-muted hover:text-ink-text'
                            }`}
                        >
                            {opt.label}
                        </button>
                    ))}
                </div>

                <button
                    type="button"
                    onClick={() => {
                        const idx = windowOptions.findIndex((w) => w.value === filters.window);
                        navigate({ window: windowOptions[(idx + 1) % windowOptions.length].value });
                    }}
                    className="chip shrink-0"
                    aria-label="Change time window"
                >
                    {windowOptions.find((w) => w.value === filters.window)?.label}
                    <span className="opacity-60">▾</span>
                </button>

                <div className="flex shrink-0 items-center gap-2 text-[13px] text-ink-muted">
                    <button
                        type="button"
                        role="switch"
                        aria-checked={filters.explicit}
                        aria-label="Show explicit"
                        onClick={() => (auth.user ? navigate({ explicit: !filters.explicit }) : router.post(route('age-gate.confirm')))}
                        className={`relative h-5 w-9 shrink-0 rounded-full transition-colors ${filters.explicit ? 'bg-ink-brand/40' : 'bg-[#3a3242]'}`}
                    >
                        <span
                            className={`absolute top-0.5 h-4 w-4 rounded-full transition-all ${
                                filters.explicit ? 'left-[18px] bg-ink-brand' : 'left-0.5 bg-[#8d8297]'
                            }`}
                        />
                    </button>
                    <span className="hidden sm:inline">Show explicit</span>
                </div>
            </div>
        </>
    );

    return (
        <AppLayout>
            <Head title="Gallery" />
            <TopBar
                title="Gallery"
                subtitle={`${stats.works.toLocaleString()} works · ${stats.artists.toLocaleString()} artists`}
                tabs={tabs}
            />

            <div className="px-2.5 pb-16 pt-3 md:px-5 md:pt-4">
                {posts.length === 0 ? (
                    <div className="grid place-items-center gap-3 py-24 text-center text-ink-muted">
                        <p className="text-[15px]">Nothing in this filter yet.</p>
                        <Link href={route('upload.create')} className="btn-primary">
                            Be the first to post
                        </Link>
                    </div>
                ) : (
                    <Masonry posts={posts} />
                )}
                {hasMore && (
                    <div className="grid place-items-center py-8">
                        <span className="text-sm text-ink-muted">Showing the first {posts.length} works — refine the filters to narrow it down.</span>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
