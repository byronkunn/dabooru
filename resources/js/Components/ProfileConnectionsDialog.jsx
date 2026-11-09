import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';
import { Icon } from '@/Components/Icons';

export default function ProfileConnectionsDialog({ username, type, onClose }) {
    const [people, setPeople] = useState([]);
    const [page, setPage] = useState(1);
    const [retry, setRetry] = useState(0);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    const title = type === 'followers' ? 'Followers' : 'Following';

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(false);
        axios.get(route('profile.connections', { username, type }), {
            params: { page },
            signal: controller.signal,
        }).then(({ data }) => {
            if (controller.signal.aborted) return;
            setPeople((previous) => page === 1 ? data.data : [...previous, ...data.data]);
            setHasMore(data.has_more);
        }).catch((failure) => {
            if (!axios.isCancel(failure)) setError(true);
        }).finally(() => {
            if (!controller.signal.aborted) setLoading(false);
        });
        return () => controller.abort();
    }, [username, type, page, retry]);

    return (
        <Dialog open onClose={onClose} className="fixed inset-0 z-[80]">
            <div className="fixed inset-0 bg-black/70" aria-hidden="true" />
            <div className="fixed inset-0 flex items-center justify-center p-4">
                <DialogPanel className="flex max-h-[80dvh] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-ink-line bg-ink-surface">
                    <div className="flex items-center gap-3 border-b border-ink-line p-4">
                        <div className="min-w-0">
                            <DialogTitle className="text-lg font-bold">{title}</DialogTitle>
                            <p className="truncate text-sm text-ink-muted">@{username}</p>
                        </div>
                        <button type="button" onClick={onClose} aria-label="Close" className="btn-icon-flat ml-auto shrink-0">
                            <Icon name="close" />
                        </button>
                    </div>
                    <div className="overflow-y-auto p-4" aria-busy={loading}>
                        <ul className="space-y-3">
                            {people.map((person) => (
                                <li key={person.id}>
                                    <Link href={person.url} onClick={onClose} className="flex min-w-0 items-center gap-3 rounded-lg p-2 hover:bg-ink-raised">
                                        <img src={person.avatar} alt="" className="h-11 w-11 shrink-0 rounded-full object-cover" />
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold">{person.name}</p>
                                            <p className="truncate text-sm text-ink-muted">@{person.username}</p>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        {loading && <p role="status" className="py-6 text-center text-sm text-ink-muted">Loading accounts...</p>}
                        {error && (
                            <div role="alert" className="py-4 text-center">
                                <p className="mb-3 text-sm text-ink-muted">Could not load accounts. Please try again.</p>
                                <button type="button" className="btn-ghost" onClick={() => setRetry((value) => value + 1)}>Try again</button>
                            </div>
                        )}
                        {!loading && !error && people.length === 0 && (
                            <p className="py-6 text-center text-sm text-ink-muted">
                                {type === 'followers' ? 'No followers yet.' : 'Not following anyone yet.'}
                            </p>
                        )}
                        {!loading && !error && hasMore && (
                            <button type="button" className="btn-ghost mt-4 w-full" onClick={() => {
                                setLoading(true);
                                setPage((value) => value + 1);
                            }}>Load more</button>
                        )}
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    );
}
