import { Link } from '@inertiajs/react';

export function AdminPagination({ previousPage, nextPage }) {
    if (!previousPage && !nextPage) return null;

    return (
        <nav aria-label="Page navigation" className="flex flex-wrap gap-2">
            {previousPage && <Link href={previousPage} className="btn-ghost">Previous page</Link>}
            {nextPage && <Link href={nextPage} className="btn-ghost">Next page</Link>}
        </nav>
    );
}

export function AdminErrors({ errors }) {
    const messages = Object.values(errors ?? {});
    return messages.length > 0 && (
        <ul role="alert" className="space-y-1 text-sm text-ink-expl">
            {messages.map((message, index) => <li key={index}>{message}</li>)}
        </ul>
    );
}

export const actionLabel = (action) => action.replaceAll('_', ' ');
