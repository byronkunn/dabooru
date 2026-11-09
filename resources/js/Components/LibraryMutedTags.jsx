import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { AdminErrors } from '@/Components/AdminUi';

export default function LibraryMutedTags({ tags }) {
    const form = useForm({ exclude_tags: tags.join(', ') });
    useEffect(() => form.setData('exclude_tags', tags.join(', ')), [tags]);

    return <details className="min-w-0">
        <summary className="btn-ghost cursor-pointer">Muted tags{tags.length ? ` (${tags.length})` : ''}</summary>
        <form className="mt-2 flex max-w-full flex-wrap gap-2" onSubmit={(event) => {
            event.preventDefault();
            form.patch(route('library.muted-tags.update'), { preserveScroll: true });
        }}>
            <label className="field-label">Hide works with tags
                <input className="field-input mt-1" maxLength={2000} value={form.data.exclude_tags}
                    onChange={(event) => form.setData('exclude_tags', event.target.value)} placeholder="Comma-separated tags" />
            </label>
            <button className="btn-ghost self-end" disabled={form.processing}>Apply muted tags</button>
            <AdminErrors errors={form.errors} />
        </form>
    </details>;
}
