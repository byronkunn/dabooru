import { Link } from '@inertiajs/react';

const ADMIN_NAV = [
    ['admin.reports', 'Reports'],
    ['admin.users', 'Users'],
    ['admin.log', 'Moderation log'],
];

export function AdminTabs() {
    return (
        <div className="flex gap-1.5 overflow-x-auto border-b border-ink-line px-3.5 py-2.5 md:px-5">
            {ADMIN_NAV.map(([routeName, label]) => (
                <Link key={routeName} href={route(routeName)} className={route().current(routeName) ? 'chip is-active' : 'chip'}>
                    {label}
                </Link>
            ))}
        </div>
    );
}
