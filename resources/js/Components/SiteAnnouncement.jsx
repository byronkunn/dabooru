import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Icon } from '@/Components/Icons';

const dismissedAnnouncements = new Set();

export default function SiteAnnouncement() {
    const { announcement } = usePage().props;
    const [, refresh] = useState(0);
    if (!announcement) return null;
    const key = `${announcement.id}:${announcement.updated_at}:${announcement.title}:${announcement.body}`;
    if (dismissedAnnouncements.has(key)) return null;

    return (
        <aside aria-label="Site announcement" className="flex items-start gap-2 border-b border-ink-brand/25 bg-ink-brand/5 px-3.5 py-2 text-sm">
            <Icon name="bell" className="mt-1 h-4 w-4 shrink-0 text-ink-brand" />
            <details className="min-w-0 flex-1">
                <summary className="cursor-pointer break-words font-semibold">{announcement.title}</summary>
                <p className="mt-2 whitespace-pre-wrap break-words text-ink-muted">{announcement.body}</p>
            </details>
            <button type="button" aria-label="Dismiss announcement" onClick={() => {
                dismissedAnnouncements.add(key);
                refresh((value) => value + 1);
            }} className="btn-icon-flat -my-1 h-9 w-9 shrink-0"><Icon name="close" className="h-4 w-4" /></button>
        </aside>
    );
}
