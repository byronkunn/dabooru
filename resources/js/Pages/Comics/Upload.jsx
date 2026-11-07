import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';
import { useRef, useState } from 'react';

const RATINGS = [
    { key: 'sfw', label: 'SFW' },
    { key: 'suggestive', label: 'Suggestive' },
    { key: 'explicit', label: 'Explicit · 18+' },
];

export default function ComicUpload({ comics }) {
    const { auth } = usePage().props;
    const [mode, setMode] = useState(comics.length ? 'chapter' : 'series');
    const [selected, setSelected] = useState(comics[0]?.slug ?? null);
    const [pages, setPages] = useState([]);
    const pageInput = useRef(null);

    const seriesForm = useForm({ title: '', description: '', rating: 'sfw', status: 'ongoing' });
    const chapterForm = useForm({ number: 1, title: '', rating: 'sfw', pages: [], publish: true });

    const submitSeries = (e) => {
        e.preventDefault();
        seriesForm.post(route('comics.series.store'));
    };

    const onPages = (e) => {
        const files = Array.from(e.target.files ?? []);
        setPages(files.map((f) => ({ file: f, url: URL.createObjectURL(f) })));
        chapterForm.setData('pages', files);
    };

    const submitChapter = (e) => {
        e.preventDefault();
        chapterForm.post(route('comics.chapters.store', selected), { forceFormData: true });
    };

    const activeComic = comics.find((c) => c.slug === selected);

    return (
        <AppLayout>
            <Head title="New comic" />
            <TopBar title="Comics" subtitle="Series and chapters" back={route('comics.index')} />

            <div className="mx-auto max-w-[760px] p-3.5 md:p-5">
                <div className="mb-4 flex gap-2">
                    <button type="button" onClick={() => setMode('chapter')} disabled={!comics.length} className={`chip ${mode === 'chapter' ? 'is-active' : ''}`}>
                        New chapter
                    </button>
                    <button type="button" onClick={() => setMode('series')} className={`chip ${mode === 'series' ? 'is-active' : ''}`}>
                        New series
                    </button>
                </div>

                {mode === 'series' ? (
                    <form onSubmit={submitSeries} className="panel space-y-3.5">
                        <div>
                            <label htmlFor="title" className="field-label">Series title</label>
                            <input id="title" value={seriesForm.data.title} onChange={(e) => seriesForm.setData('title', e.target.value)} className="field-input" maxLength={140} required />
                            {seriesForm.errors.title && <p className="field-hint text-ink-expl">{seriesForm.errors.title}</p>}
                        </div>
                        <div>
                            <label htmlFor="description" className="field-label">Description</label>
                            <textarea id="description" rows={3} value={seriesForm.data.description} onChange={(e) => seriesForm.setData('description', e.target.value)} className="field-input resize-none" maxLength={2000} />
                        </div>
                        <div className="grid gap-3.5 sm:grid-cols-2">
                            <div>
                                <label className="field-label">Rating</label>
                                <select value={seriesForm.data.rating} onChange={(e) => seriesForm.setData('rating', e.target.value)} className="field-input">
                                    {RATINGS.map((r) => (
                                        <option key={r.key} value={r.key}>{r.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="field-label">Status</label>
                                <select value={seriesForm.data.status} onChange={(e) => seriesForm.setData('status', e.target.value)} className="field-input">
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="hiatus">Hiatus</option>
                                </select>
                            </div>
                        </div>
                        <div className="flex justify-end">
                            <button type="submit" disabled={seriesForm.processing} className="btn-primary">Create series</button>
                        </div>
                    </form>
                ) : (
                    <form onSubmit={submitChapter} className="panel space-y-3.5">
                        <div>
                            <label htmlFor="series" className="field-label">Series</label>
                            <select id="series" value={selected ?? ''} onChange={(e) => setSelected(e.target.value)} className="field-input">
                                {comics.map((c) => (
                                    <option key={c.id} value={c.slug}>
                                        {c.title} ({c.chapters} chapters)
                                    </option>
                                ))}
                            </select>
                        </div>

                        <input ref={pageInput} type="file" accept="image/*" multiple onChange={onPages} className="sr-only" id="pages" />
                        <label htmlFor="pages" className="grid min-h-[180px] cursor-pointer place-items-center rounded-[18px] border-2 border-dashed border-[#4a3c58] p-6 text-center hover:border-ink-brand">
                            <div>
                                <Icon name="upload" className="mx-auto mb-2 h-8 w-8 text-ink-muted" />
                                <p className="text-[15px] font-bold">{pages.length ? `${pages.length} pages selected` : 'Add chapter pages'}</p>
                                <p className="mt-1 text-[12.5px] text-ink-muted">JPG, PNG or WebP · up to 15MB each</p>
                            </div>
                        </label>
                        {chapterForm.errors.pages && <p className="field-hint text-ink-expl">{chapterForm.errors.pages}</p>}

                        {pages.length > 0 && (
                            <div className="grid grid-cols-4 gap-1.5 sm:grid-cols-6">
                                {pages.map((p, i) => (
                                    <div key={i} className="relative overflow-hidden rounded-lg border border-ink-line">
                                        <img src={p.url} alt="" className="aspect-[3/4] w-full object-cover" />
                                        <span className="absolute left-1 top-1 rounded bg-black/70 px-1 text-[10px] font-bold">{i + 1}</span>
                                    </div>
                                ))}
                            </div>
                        )}

                        <div className="grid gap-3.5 sm:grid-cols-2">
                            <div>
                                <label htmlFor="number" className="field-label">Chapter number</label>
                                <input id="number" type="number" min={1} value={chapterForm.data.number} onChange={(e) => chapterForm.setData('number', Number(e.target.value))} className="field-input" />
                            </div>
                            <div>
                                <label htmlFor="ctitle" className="field-label">Chapter title</label>
                                <input id="ctitle" value={chapterForm.data.title} onChange={(e) => chapterForm.setData('title', e.target.value)} className="field-input" maxLength={160} />
                            </div>
                        </div>

                        <div>
                            <label className="field-label">Rating</label>
                            <div className="flex flex-wrap gap-2">
                                {RATINGS.map((r) => (
                                    <button key={r.key} type="button" onClick={() => chapterForm.setData('rating', r.key)} aria-pressed={chapterForm.data.rating === r.key} className={`min-h-11 rounded-xl border px-4 text-[13px] font-semibold ${chapterForm.data.rating === r.key ? 'border-ink-brand bg-ink-brand/10 text-ink-brand' : 'border-ink-line bg-ink-surface text-ink-muted'}`}>
                                        {r.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <label className="flex items-center gap-2.5 text-[13px] text-ink-muted">
                            <input type="checkbox" checked={chapterForm.data.publish} onChange={(e) => chapterForm.setData('publish', e.target.checked)} className="rounded border-ink-line bg-ink-raised text-ink-brand focus:ring-ink-brand" />
                            Publish to the feed now
                        </label>

                        <div className="flex items-center justify-between gap-3 border-t border-ink-line pt-3.5">
                            <Link href={activeComic ? route('comics.show', activeComic.slug) : route('comics.index')} className="text-[13px] text-ink-muted hover:text-ink-text">
                                View series
                            </Link>
                            <button type="submit" disabled={chapterForm.processing || !pages.length} className="btn-primary px-6">
                                {chapterForm.processing ? 'Publishing…' : 'Publish chapter'}
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
