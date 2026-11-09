import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AdminErrors } from '@/Components/AdminUi';
import CollectionDialog from '@/Components/CollectionDialog';
import { Icon } from '@/Components/Icons';
import { rememberWorkEngagement } from '@/Lib/workEngagement';

function CollectionPicker({ postId, onClose, onSaved }) {
    const [options, setOptions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');
    const [attempt, setAttempt] = useState(0);
    const form = useForm({ collections: [], new_name: '' });
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setLoadError('');
        fetch(route('bookmarks.collections', postId), { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(async (response) => {
                if (!response.ok) throw new Error('Could not load your collections. Try again.');
                const data = await response.json();
                setOptions(data.collections);
                form.setData('collections', data.selected);
            })
            .catch((error) => { if (!controller.signal.aborted) setLoadError(error.message); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [postId, attempt]);

    return <CollectionDialog title="Save to collection" onClose={() => { if (!form.processing) onClose(); }}>
        <p className="mb-3 text-sm text-ink-muted">Choose one or more collections. The work also stays in All Saved.</p>
        {loading ? <p role="status">Loading collections...</p> : loadError ? <div>
            <p role="alert" className="text-ink-expl">{loadError}</p>
            <button type="button" className="btn-ghost mt-2" onClick={() => setAttempt(attempt + 1)}>Try again</button>
        </div> : <form className="space-y-4" onSubmit={(event) => {
            event.preventDefault();
            form.patch(route('bookmarks.collections.update', postId), { preserveScroll: true, onSuccess: () => { onSaved(); onClose(); } });
        }}>
            <fieldset className="space-y-2">
                <legend className="field-label">Your collections</legend>
                {options.length === 0 && <p className="text-sm text-ink-muted">No collections yet. Create one below.</p>}
                {options.map((option) => <label key={option.id} className="flex min-h-11 items-center gap-3 rounded-xl border border-ink-line p-3">
                    <input type="checkbox" checked={form.data.collections.includes(option.id)} disabled={form.processing}
                        onChange={(event) => form.setData('collections', event.target.checked
                            ? [...form.data.collections, option.id] : form.data.collections.filter((id) => id !== option.id))} />
                    <span className="min-w-0 break-words">{option.name}</span>
                </label>)}
            </fieldset>
            <label className="block field-label">New collection
                <input className="field-input mt-1" maxLength={80} placeholder="Optional collection name" value={form.data.new_name}
                    disabled={form.processing} onChange={(event) => form.setData('new_name', event.target.value)} />
            </label>
            <p className="text-xs text-ink-muted">New collections are private by default.</p>
            <AdminErrors errors={form.errors} />
            <button className="btn-primary w-full" disabled={form.processing}>{form.processing ? 'Saving...' : 'Save to selected collections'}</button>
        </form>}
    </CollectionDialog>;
}

export default function SaveWorkActions({ postId, saved = false, compact = false }) {
    const { auth } = usePage().props;
    const [isSaved, setSaved] = useState(saved);
    const [picking, setPicking] = useState(false);
    const form = useForm({});
    useEffect(() => setSaved(saved), [saved]);
    useEffect(() => {
        const onSaved = (event) => {
            if (event.detail.postId === postId) setSaved(event.detail.saved);
        };
        window.addEventListener('work-saved', onSaved);
        return () => window.removeEventListener('work-saved', onSaved);
    }, [postId]);
    const remember = (value) => {
        setSaved(value);
        if (auth.user) rememberWorkEngagement(auth.user.id, postId, 'saved', value);
        window.dispatchEvent(new CustomEvent('work-saved', { detail: { postId, saved: value } }));
    };
    return <div className="flex min-w-0 flex-wrap items-center gap-2">
        <button type="button" aria-pressed={isSaved} disabled={form.processing}
            className={`${compact ? 'min-h-9 rounded-full px-2 text-xs' : 'btn-ghost'} flex items-center gap-1.5 ${isSaved ? 'text-ink-brand' : ''}`}
            onClick={() => {
                if (!auth.user) { router.get(route('login')); return; }
                const method = isSaved ? 'delete' : 'post';
                form[method](route(isSaved ? 'posts.unsave' : 'posts.save', postId), {
                    preserveScroll: true, onSuccess: () => remember(!isSaved),
                });
            }}>
            <Icon name="save" className="h-4 w-4" />{form.processing ? 'Saving...' : isSaved ? 'Saved' : 'Save'}
        </button>
        <button type="button" className={compact ? 'min-h-9 rounded-full px-2 text-xs' : 'btn-ghost'}
            onClick={() => { if (auth.user) setPicking(true); else router.get(route('login')); }}>Save to collection</button>
        <AdminErrors errors={form.errors} />
        {picking && <CollectionPicker postId={postId} onClose={() => setPicking(false)} onSaved={() => remember(true)} />}
    </div>;
}
