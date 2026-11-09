import { Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

/** Remember the last tab a reader used in a given tab group. */
export function useRememberedTab(group, current) {
    useEffect(() => {
        if (!current) return;
        try {
            window.localStorage.setItem(`inkwell.tab.${group}`, current);
        } catch {
            /* storage disabled — remembering is a nicety, not a requirement */
        }
    }, [group, current]);
}

/** Where to send someone back to for a tab group, falling back to the first item. */
export function rememberedTabHref(group, items, fallbackDestination) {
    let saved = null;
    try {
        saved = window.localStorage.getItem(`inkwell.tab.${group}`);
    } catch {
        saved = null;
    }

    const known = items.some(([, destination]) => destination === saved);

    return route(known ? saved : fallbackDestination);
}

function Tabs({ label, items }) {
    const group = label.toLowerCase().replace(/\s+/g, '-');
    const current = items.find(([, , active]) => active)?.[1] ?? null;
    useRememberedTab(group, current);

    return (
        <nav aria-label={label} className="flex flex-wrap gap-1 border-b border-ink-line px-3 py-2">
            {items.map(([name, destination, active, unread]) => (
                <Link key={destination} href={route(destination)} aria-current={active ? 'page' : undefined}
                    className={`rounded-full px-3 py-1.5 text-sm font-semibold ${active ? 'bg-ink-brand/15 text-ink-brand' : 'text-ink-muted hover:bg-ink-raised'}`}>
                    {name}{unread > 0 && <span className="ml-1.5 rounded-full bg-ink-brand/15 px-1.5 text-xs text-ink-brand" aria-label={`${unread} unread`}>{unread > 99 ? '99+' : unread}</span>}
                </Link>
            ))}
        </nav>
    );
}

export function InboxTabs() {
    const { auth } = usePage().props;
    return <Tabs label="Inbox sections" items={[
        ['Messages', 'messages.index', route().current('messages*'), auth.unread_messages],
        ['Activity', 'notifications.index', route().current('notifications*'), auth.unread_notifications],
    ]} />;
}

export const LIBRARY_TABS = [
    ['Collections', 'collections.index'],
    ['Saved Searches', 'saved-searches.index'],
];

/**
 * Library holds only Collections and Saved Searches. New Chapters lives under
 * Comics, where chapter updates belong.
 */
export function LibraryTabs() {
    return <Tabs label="Library sections" items={LIBRARY_TABS.map(([name, destination]) => [
        name, destination, route().current(destination.replace('.index', '') + '*'),
    ])} />;
}

export const COMICS_TABS = [
    ['Comics', 'comics.index'],
    ['New Chapters', 'chapters.inbox'],
];

/** Comics and the chapter inbox share one group, so returning to Comics keeps the reader oriented. */
export function ComicsTabs() {
    return <Tabs label="Comics sections" items={COMICS_TABS.map(([name, destination]) => [
        name, destination, route().current(destination.replace('.index', '') + '*'),
    ])} />;
}
