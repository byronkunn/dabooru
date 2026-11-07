import { Link, useForm } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';

export function Errors({ errors }) {
    return Object.keys(errors).length > 0 && (
        <div role="alert" className="my-3 rounded-xl border border-red-400/40 p-3 text-sm text-red-300">
            {Object.entries(errors).map(([key, message]) => <p key={key}>{message}</p>)}
        </div>
    );
}

export function Pagination({ data }) {
    if (!data?.prev_page_url && !data?.next_page_url) return null;
    return (
        <nav aria-label="Pagination" className="my-4 flex items-center gap-3">
            {data.prev_page_url && <Link href={data.prev_page_url} preserveState preserveScroll className="btn-ghost">Previous</Link>}
            <span className="text-sm text-ink-muted">Page {data.current_page} of {data.last_page}</span>
            {data.next_page_url && <Link href={data.next_page_url} preserveState preserveScroll className="btn-ghost">Next</Link>}
        </nav>
    );
}

export function RevisionList({ revisions }) {
    return (
        <div>
            {revisions.data.length === 0 && <p className="text-ink-muted">No visible revisions yet.</p>}
            {revisions.data.map((revision) => (
                <div key={revision.id} className="border-b border-ink-line py-3">
                    <Link href={revision.url} className="font-semibold text-ink-brand hover:underline">{revision.summary}</Link>
                    <p className="text-sm text-ink-muted">
                        {revision.status} · {revision.editor ? `@${revision.editor}` : 'Deleted contributor'}
                        {' · '}{new Date(revision.created_at).toLocaleString()}
                    </p>
                </div>
            ))}
            <Pagination data={revisions} />
        </div>
    );
}

export function ReportKnowledge({ type, id }) {
    const form = useForm({ target_type: type, target_id: id, reason: '' });
    return (
        <details className="my-3 text-sm">
            <summary className="cursor-pointer text-ink-muted">Report this {type}</summary>
            <form onSubmit={(e) => { e.preventDefault(); form.post(route('knowledge.report'), { preserveScroll: true, onSuccess: () => form.reset('reason') }); }} className="mt-2 flex flex-col gap-2">
                <label>Reason<input className="field-input" required minLength={3} maxLength={500} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} /></label>
                <Errors errors={form.errors} />
                <button disabled={form.processing} className="btn-ghost self-start">Send report</button>
            </form>
        </details>
    );
}

export function TagPicker({ label, selected, onChange, namesOnly = false }) {
    const id = useId();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    useEffect(() => {
        const controller = new AbortController();
        if (!query.trim()) {
            setResults([]);
            setLoading(false);
            setError('');
            return () => controller.abort();
        }
        const timeout = setTimeout(async () => {
            setLoading(true);
            setError('');
            try {
                const response = await fetch(route('knowledge.autocomplete', { q: query }), { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`Tag lookup failed (${response.status}).`);
                const data = await response.json();
                setResults(data.tags);
            } catch (err) {
                if (err.name !== 'AbortError') setError(err.message);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 200);
        return () => { controller.abort(); clearTimeout(timeout); };
    }, [query]);
    const add = (tag) => {
        if (!selected.some((entry) => namesOnly ? entry.name === tag.name : entry.id === tag.id)) onChange([...selected, tag]);
        setQuery('');
        setResults([]);
    };
    return (
        <div className="my-3">
            <label htmlFor={id} className="field-label">{label}</label>
            <div className="mb-2 flex flex-wrap gap-2">
                {selected.map((tag) => (
                    <button key={tag.id ?? tag.name} type="button" className="chip" onClick={() => onChange(selected.filter((item) => item !== tag))} aria-label={`Remove ${tag.name}`}>
                        {tag.name} ×
                    </button>
                ))}
            </div>
            <input id={id} className="field-input" maxLength={40} value={query} onChange={(e) => { setQuery(e.target.value); setResults([]); }} placeholder={namesOnly ? 'Search or enter a tag' : 'Search tags'} onKeyDown={(e) => {
                if (e.key === 'Escape') { setQuery(''); setResults([]); }
                if (e.key === 'Enter' && query.trim()) {
                    e.preventDefault();
                    if (namesOnly && !query.includes(',')) add({ name: query.trim().toLowerCase() });
                    else if (results.length > 0) add(results[0]);
                }
            }} />
            {loading && <p role="status" className="text-sm text-ink-muted">Looking up tags...</p>}
            {error && <p role="alert" className="text-sm text-red-300">{error}</p>}
            <div className="mt-1 flex flex-col gap-1">
                {results.map((tag) => (
                    <button key={tag.id} type="button" className="rounded-xl bg-ink-raised p-2 text-left hover:text-ink-brand" onClick={() => add(tag)}>
                        <b>{tag.name}</b> <span className="text-xs text-ink-muted">{tag.category} · {tag.count} works</span>
                        {tag.definition && <span className="block text-xs text-ink-muted">{tag.definition}</span>}
                    </button>
                ))}
                {namesOnly && query.trim() && !query.includes(',') && (
                    <button type="button" className="btn-ghost self-start" onClick={() => add({ name: query.trim().toLowerCase() })}>Add "{query.trim()}"</button>
                )}
            </div>
        </div>
    );
}
