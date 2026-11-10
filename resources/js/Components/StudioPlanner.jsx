import { router, useForm } from '@inertiajs/react';
import { useEffect, useId, useMemo, useState } from 'react';
import { AdminErrors, AdminPagination } from '@/Components/AdminUi';

const stages = ['idea', 'in_progress', 'draft', 'ready', 'scheduled', 'published'];
const label = (stage) => stage === 'in_progress' ? 'In progress' : stage.charAt(0).toUpperCase() + stage.slice(1);
const localInput = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (number) => String(number).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};
const utc = (value) => value ? new Date(value).toISOString() : null;
const formatTime = (value) => value ? new Date(value).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : 'Not set';

export default function StudioPlanner({ filters = {}, plans = [], planStage = null, planCounts = {}, planPages = {}, draftWorks = [], draftChapters = [] }) {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    const prefix = useId();
    const [editing, setEditing] = useState(null);
    const [composing, setComposing] = useState(false);
    const [view, setView] = useState('board');
    const [deleting, setDeleting] = useState(null);
    const blank = { title: '', notes: '', stage: 'idea', deadline_at: '', publish_at: '', timezone, destination: '' };
    const form = useForm(blank);
    const deletion = useForm({});
    const filter = planStage || 'all';
    const totalPlans = Object.values(planCounts).reduce((sum, count) => sum + count, 0);
    // First-time users see the form straight away; everyone else gets the board first.
    const formOpen = composing || Boolean(editing) || totalPlans === 0;
    // Stage filtering happens server-side so it spans every page, not just the loaded one.
    const visible = plans;
    const changeFilter = (stage) => router.get(route('studio.index'), {
        ...filters, tab: 'planner', ...(stage === 'all' ? {} : { stage }),
    }, { preserveScroll: true, preserveState: true });
    const agenda = useMemo(() => {
        const groups = new Map();
        [...visible].sort((a, b) => (a.publish_at || a.deadline_at || '9999').localeCompare(b.publish_at || b.deadline_at || '9999')).forEach((plan) => {
            const date = plan.publish_at || plan.deadline_at;
            const key = date ? localInput(date).slice(0, 10) : 'No date';
            groups.set(key, [...(groups.get(key) || []), plan]);
        });
        return [...groups];
    }, [visible]);

    function reset() {
        setEditing(null);
        setComposing(false);
        form.setData(blank);
        form.clearErrors();
    }

    function edit(plan) {
        setEditing(plan);
        form.clearErrors();
        form.setData({
            title: plan.title, notes: plan.notes || '', stage: plan.stage,
            deadline_at: localInput(plan.deadline_at), publish_at: localInput(plan.publish_at), timezone,
            destination: plan.post_id ? `work:${plan.post_id}` : plan.comic_chapter_id ? `chapter:${plan.comic_chapter_id}` : '',
        });
    }

    // Focus the title once the form has actually rendered (it is collapsed until needed).
    useEffect(() => {
        if (formOpen) document.getElementById(`${prefix}-title`)?.focus();
    }, [formOpen, editing?.id]);

    function submit(event) {
        event.preventDefault();
        form.transform(({ destination, ...data }) => {
            const [kind, id] = destination.split(':');
            return {
                ...data, timezone, deadline_at: utc(data.deadline_at), publish_at: utc(data.publish_at),
                post_id: kind === 'work' ? Number(id) : null,
                comic_chapter_id: kind === 'chapter' ? Number(id) : null,
            };
        });
        const options = { preserveScroll: true, onSuccess: reset };
        if (editing) form.patch(route('studio.plans.update', { plan: editing.id }), options);
        else form.post(route('studio.plans.store'), options);
    }

    function remove(plan) {
        if (!window.confirm(`Delete “${plan.title}”? Linked drafts and uploads will be kept.`)) return;
        deletion.clearErrors();
        setDeleting(plan.id);
        deletion.delete(route('studio.plans.destroy', { plan: plan.id }), {
            preserveScroll: true, onSuccess: () => { if (editing?.id === plan.id) reset(); },
            onFinish: () => setDeleting(null),
        });
    }

    const field = 'field-input mt-1 w-full min-w-0 max-w-full';
    const button = 'btn-ghost min-h-11';
    const error = (name) => form.errors[name] && <p id={`${prefix}-${name}-error`} className="mt-1 text-sm text-red-300" role="alert">{form.errors[name]}</p>;
    const linkedIsAvailable = !form.data.destination || [
        ...draftWorks.map((work) => `work:${work.id}`),
        ...draftChapters.map((chapter) => `chapter:${chapter.id}`),
    ].includes(form.data.destination);
    const card = (plan) => (
        <article key={plan.id} className="min-w-0 rounded-xl border border-ink-line bg-ink-surface p-4">
            <h4 className="break-words font-semibold">{plan.title}</h4>
            <p className="mt-1 text-xs text-ink-muted">{label(plan.stage)} · Private</p>
            {plan.notes && <p className="mt-3 whitespace-pre-wrap break-words text-sm">{plan.notes}</p>}
            <dl className="mt-3 space-y-1 text-xs text-ink-muted">
                <div><dt className="inline font-medium">Deadline: </dt><dd className="inline">{formatTime(plan.deadline_at)}</dd></div>
                <div><dt className="inline font-medium">Publish: </dt><dd className="inline">{formatTime(plan.publish_at)}</dd></div>
                {(plan.post_id || plan.comic_chapter_id) && <div><dt className="inline font-medium">Linked: </dt><dd className="inline">{plan.post_id ? `Work #${plan.post_id}` : `Chapter #${plan.comic_chapter_id}`}</dd></div>}
                {plan.timezone && <div><dt className="inline font-medium">Saved timezone: </dt><dd className="inline break-words">{plan.timezone}</dd></div>}
            </dl>
            {plan.delivery_status === 'pending' && <p className="mt-2 text-sm">Scheduled for publication. If an upload is still processing at that time, it will publish once ready.</p>}
            {plan.delivery_status === 'published' && <p className="mt-2 text-sm">Linked draft published successfully.</p>}
            {plan.delivery_status === 'failed' && <p role="alert" className="mt-2 break-words text-sm text-red-300">Publication failed: {plan.delivery_error || 'Review the draft and reschedule.'}</p>}
            <div className="mt-3 flex flex-wrap gap-2">
                <button type="button" className={button} disabled={form.processing} onClick={() => edit(plan)} aria-label={`Edit ${plan.title}`}>Edit{plan.stage === 'scheduled' ? ' / cancel schedule' : ''}</button>
                <button type="button" className={button} disabled={form.processing || deletion.processing} onClick={() => remove(plan)} aria-label={`Delete ${plan.title}`}>{deleting === plan.id ? 'Deleting…' : 'Delete'}</button>
            </div>
        </article>
    );

    return (
        <section className="min-w-0 space-y-5" aria-labelledby={`${prefix}-heading`}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 id={`${prefix}-heading`} className="text-lg font-semibold">Private planner</h2>
                    <p className="mt-1 text-sm text-ink-muted">Ideas and notes stay private. Scheduling publishes the linked draft, not these notes. All times shown and entered in {timezone}; stored as UTC.</p>
                    <details className="mt-1 text-xs text-ink-muted">
                        <summary className="cursor-pointer py-1 text-ink-brand">How scheduling and cancelling work</summary>
                        <p className="mt-1">Scheduled releases are published by the server scheduler. Late releases run on its next check; failures appear here for review.</p>
                        <p className="mt-1">To cancel a schedule, change its stage to Ready (or another unscheduled stage) and save. To retry a failed publication, choose a future time and save as Scheduled.</p>
                    </details>
                </div>
                {!formOpen && <button type="button" className="btn-primary min-h-11 shrink-0" onClick={() => setComposing(true)}>New plan</button>}
            </div>
            <AdminErrors errors={deletion.errors} />
            {formOpen && <form onSubmit={submit} className="min-w-0 rounded-xl border border-ink-line bg-ink-surface p-4">
                <h3 className="font-semibold">{editing ? 'Edit plan' : 'New plan'}</h3>
                <div className="mt-3 grid min-w-0 gap-4 sm:grid-cols-2">
                    <label className="min-w-0 text-sm" htmlFor={`${prefix}-title`}>Title
                        <input id={`${prefix}-title`} className={field} required maxLength={160} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} aria-invalid={!!form.errors.title} />
                        {error('title')}
                    </label>
                    <label className="min-w-0 text-sm" htmlFor={`${prefix}-stage`}>Stage
                        <select id={`${prefix}-stage`} className={field} value={form.data.stage} onChange={(e) => form.setData('stage', e.target.value)}>
                            {stages.map((stage) => <option key={stage} value={stage}>{label(stage)}</option>)}
                        </select>
                        {error('stage')}
                    </label>
                    <label className="min-w-0 text-sm sm:col-span-2" htmlFor={`${prefix}-notes`}>Private notes
                        <textarea id={`${prefix}-notes`} className={field} rows={3} maxLength={10000} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                        {error('notes')}
                    </label>
                    <label className="min-w-0 text-sm" htmlFor={`${prefix}-deadline`}>Deadline ({timezone})
                        <input id={`${prefix}-deadline`} type="datetime-local" className={field} value={form.data.deadline_at} onChange={(e) => form.setData('deadline_at', e.target.value)} />
                        {error('deadline_at')}
                    </label>
                    <label className="min-w-0 text-sm" htmlFor={`${prefix}-publish`}>Planned publish time ({timezone})
                        <input id={`${prefix}-publish`} type="datetime-local" className={field} required={form.data.stage === 'scheduled'} value={form.data.publish_at} onChange={(e) => form.setData('publish_at', e.target.value)} />
                        {error('publish_at')}
                    </label>
                    <label className="min-w-0 text-sm sm:col-span-2" htmlFor={`${prefix}-destination`}>Linked draft (optional unless scheduled)
                        <select id={`${prefix}-destination`} className={field} value={form.data.destination} onChange={(e) => form.setData('destination', e.target.value)}>
                            <option value="">No linked draft</option>
                            {!linkedIsAvailable && <option value={form.data.destination}>Current linked draft (published or unavailable)</option>}
                            <optgroup label="Work drafts">{draftWorks.map((work) => <option key={work.id} value={`work:${work.id}`}>{work.title || `Work #${work.id}`}</option>)}</optgroup>
                            <optgroup label="Comic chapter drafts">{draftChapters.map((chapter) => <option key={chapter.id} value={`chapter:${chapter.id}`}>{chapter.series || 'Comic'} · Ch. {chapter.number}: {chapter.title || 'Untitled'}</option>)}</optgroup>
                        </select>
                        {error('post_id')}{error('comic_chapter_id')}
                    </label>
                </div>
                {error('timezone')}
                <div className="mt-3 flex flex-wrap gap-2">
                    <button className="btn-primary min-h-11" type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : editing ? 'Save plan' : 'Create plan'}</button>
                    {(editing || totalPlans > 0) && <button className={button} type="button" onClick={reset} disabled={form.processing}>{editing ? 'Cancel editing' : 'Cancel'}</button>}
                </div>
            </form>}
            <div className="flex flex-wrap items-end gap-3">
                <label className="text-sm" htmlFor={`${prefix}-filter`}>Stage filter
                    <select id={`${prefix}-filter`} className={field} value={filter} onChange={(e) => changeFilter(e.target.value)}>
                        <option value="all">All stages</option>
                        {stages.map((stage) => <option key={stage} value={stage}>{label(stage)}</option>)}
                    </select>
                </label>
                <div className="flex flex-wrap gap-2" role="group" aria-label="Planner view">
                    <button type="button" className={button} aria-pressed={view === 'board'} onClick={() => setView('board')}>Board</button>
                    <button type="button" className={button} aria-pressed={view === 'calendar'} onClick={() => setView('calendar')}>Calendar agenda</button>
                </div>
            </div>
            {visible.length === 0 ? <p className="text-sm text-ink-muted">No plans in this view.</p> : view === 'board' ? (
                <div className="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {stages.filter((stage) => filter === 'all' || filter === stage).map((stage) => (
                        <section key={stage} className="min-w-0 space-y-3" aria-label={`${label(stage)} plans`}>
                            <h3 className="font-semibold">{label(stage)} <span className="text-ink-muted">({planCounts[stage] ?? 0})</span></h3>
                            {visible.filter((plan) => plan.stage === stage).map(card)}
                        </section>
                    ))}
                </div>
            ) : (
                <div className="space-y-5">{agenda.map(([date, entries]) => (
                    <section key={date} aria-label={`Plans for ${date}`} className="min-w-0 space-y-3">
                        <h3 className="font-semibold">{date}</h3>
                        <div className="grid min-w-0 gap-3 md:grid-cols-2">{entries.map(card)}</div>
                    </section>
                ))}</div>
            )}
            <AdminPagination {...planPages} />
        </section>
    );
}
