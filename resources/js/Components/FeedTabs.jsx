import { Link } from '@inertiajs/react';

export default function FeedTabs() {
    return (
        <nav aria-label="Feeds" className="flex border-t border-ink-line">
            {[['Gallery', 'gallery'], ['Discover', 'discover'], ['Following', 'following']].map(([label, name]) => (
                <Link key={name} href={route(name)} aria-current={route().current(name) ? 'page' : undefined}
                    className={`min-h-11 flex-1 py-3.5 text-center text-sm font-semibold ${route().current(name) ? 'border-b-2 border-ink-brand text-ink-brand' : 'text-ink-muted hover:text-ink-text'}`}>
                    {label}
                </Link>
            ))}
        </nav>
    );
}
