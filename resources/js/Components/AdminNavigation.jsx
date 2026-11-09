import { Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { Icon } from '@/Components/Icons';

export const ADMIN_NAV = [
    { label: 'Reports', href: 'admin.reports', icon: 'flag', route: 'admin.reports*', permission: 'moderator' },
    { label: 'People & roles', shortLabel: 'People', href: 'admin.users', icon: 'user', route: 'admin.users*', permission: 'admin' },
    { label: 'Announcements', shortLabel: 'Notices', href: 'admin.announcements.index', icon: 'bell', route: 'admin.announcements.*', permission: 'admin' },
    { label: 'Community edits', shortLabel: 'Community', href: 'admin.knowledge', icon: 'bookmark', route: 'admin.knowledge*', permission: 'curator' },
    { label: 'Tags & merges', shortLabel: 'Tags', href: 'admin.tags.index', icon: 'bookmark', route: 'admin.tags.*', permission: 'admin' },
    { label: 'Curated shelves', shortLabel: 'Shelves', href: 'admin.shelves', icon: 'gallery', route: 'admin.shelves*', permission: 'curator' },
    { label: 'Artist credit claims', shortLabel: 'Credits', href: 'admin.credit-claims', icon: 'user', route: 'admin.credit-claims*', permission: 'curator' },
    { label: 'DMCA notices', shortLabel: 'DMCA', href: 'admin.dmca', icon: 'flag', route: 'admin.dmca*', permission: 'admin' },
    { label: 'Moderation log', shortLabel: 'Audit', href: 'admin.log', icon: 'following', route: 'admin.log', permission: 'admin' },
    { label: 'Site logs', shortLabel: 'Logs', href: 'admin.site-logs', icon: 'settings', route: 'admin.site-logs', permission: 'operator' },
    { label: 'Analytics', href: 'admin.analytics', icon: 'analytics', route: 'admin.analytics', permission: 'analyst' },
];

export function adminLinks(user) {
    return ADMIN_NAV.filter((item) => user?.staff_permissions?.includes(item.permission));
}

/**
 * Mobile admin rail.
 *
 * Below lg the desktop rail is hidden, so instead of a bottom tab bar this is a
 * hamburger that slides a rail in over the content. Nothing reflows while it is
 * open — it is position:fixed, so the page underneath keeps its layout.
 */
export function AdminMobileRail({ open, onClose }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const links = adminLinks(user);

    // Close on route change.
    useEffect(() => {
        onClose();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [typeof window !== 'undefined' ? window.location.pathname : '']);

    // Close on Escape.
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    return (
        <>
            {/* scrim: dismisses the rail, sits under it */}
            <div
                aria-hidden="true"
                onClick={onClose}
                className={`fixed inset-0 z-[60] bg-black/60 transition-opacity duration-200 lg:hidden ${
                    open ? 'pointer-events-auto opacity-100' : 'pointer-events-none opacity-0'
                }`}
            />

            <aside
                aria-label="Admin navigation"
                aria-hidden={!open}
                className={`fixed left-0 top-0 z-[61] flex h-full w-[264px] flex-col gap-1 overflow-y-auto border-r border-ink-line bg-ink-bg px-3 py-5 pb-safe shadow-2xl transition-transform duration-200 ease-out lg:hidden ${
                    open ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="mb-4 flex items-center gap-2.5 px-2">
                    <Link href={route(links[0]?.href ?? 'admin.reports')} aria-label="ModernBooru admin" className="flex items-center gap-2.5 font-extrabold tracking-[-0.02em]">
                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-[9px] bg-gradient-to-br from-ink-brand to-ink-pink text-[15px] font-black text-[#1a1017]">
                            M
                        </span>
                        <span className="text-[17px]">ModernBooru admin</span>
                    </Link>
                    <button type="button" onClick={onClose} aria-label="Close navigation" className="btn-icon-flat ml-auto">
                        <Icon name="close" />
                    </button>
                </div>

                {links.map((item) => {
                    const active = item.route && route().current(item.route);
                    return (
                        <Link
                            key={item.href}
                            href={route(item.href)}
                            onClick={onClose}
                            aria-current={active ? 'page' : undefined}
                            className={`flex min-h-[44px] items-center gap-3 rounded-[11px] px-3 py-2.5 text-[15px] font-semibold ${
                                active ? 'bg-ink-raised text-ink-brand' : 'text-ink-text hover:bg-ink-raised'
                            }`}
                        >
                            <Icon name={item.icon} className="h-[21px] w-[21px] shrink-0 opacity-90" />
                            <span>{item.label}</span>
                        </Link>
                    );
                })}

                <Link href={route('gallery')} onClick={onClose} className="btn-primary mt-3">
                    <Icon name="back" className="h-5 w-5 shrink-0" />
                    Back to site
                </Link>

                {user && (
                    <div className="mt-auto flex items-center gap-2.5 rounded-xl p-2">
                        <img src={user.avatar} alt="" className="h-[38px] w-[38px] shrink-0 rounded-full object-cover" />
                        <div className="min-w-0">
                            <p className="truncate text-sm font-bold">{user.name}</p>
                            <p className="truncate text-[12.5px] capitalize text-ink-muted">
                                {user.is_admin ? 'Administrator' : user.staff_permissions?.join(', ')}
                            </p>
                        </div>
                    </div>
                )}
            </aside>
        </>
    );
}

/** The hamburger that opens the mobile admin rail. Used in AdminTopBar. */
export function AdminRailButton({ onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label="Open admin navigation"
            className="btn-icon-flat -ml-2 shrink-0 lg:hidden"
        >
            <Icon name="following" />
        </button>
    );
}
