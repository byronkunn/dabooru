import { Link, useForm, usePage } from '@inertiajs/react';
import { Dialog, DialogPanel, DialogTitle, Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { createContext, useContext, useEffect, useState } from 'react';
import { Icon } from '@/Components/Icons';
import SearchBox from '@/Components/SearchBox';
import { RATING_CLASS, workArtistName } from '@/Lib/format';
import { adminLinks, AdminMobileRail } from '@/Components/AdminNavigation';
import AdSlot from '@/Components/AdSlot';
import SiteAnnouncement from '@/Components/SiteAnnouncement';
import { AdminErrors } from '@/Components/AdminUi';
import { rememberWorkEngagement } from '@/Lib/workEngagement';
import WorkLink from '@/Components/WorkLink';
import MediaThumbnail from '@/Components/MediaThumbnail';

const FlashContext = createContext(null);
const NavigationToggleContext = createContext(null);

/* ---------------------------------------------------------------- toast */
function Toast({ message, tone, undo, onDone }) {
    const { auth } = usePage().props;
    const form = useForm({ token: undo?.token ?? '' });
    useEffect(() => {
        const t = setTimeout(onDone, undo ? 15000 : 2400);
        return () => clearTimeout(t);
    }, [message, undo, onDone]);

    return (
        <div
            role="status"
            className="fixed bottom-24 left-1/2 z-[200] w-max max-w-[calc(100vw-2rem)] -translate-x-1/2 rounded-xl border border-[#3d3447] bg-[#241f2b] px-5 py-3 text-[13.5px] shadow-2xl md:bottom-6"
        >
            {message}
            {undo && <button type="button" className="btn-ghost ml-2" disabled={form.processing} onClick={() => {
                form.transform(() => ({ token: undo.token }));
                form.post(route('bookmarks.undo'), { preserveScroll: true, onSuccess: () => {
                    if (auth.user) rememberWorkEngagement(auth.user.id, undo.postId, 'saved', true);
                    window.dispatchEvent(new CustomEvent('work-saved', { detail: { postId: undo.postId, saved: true } }));
                    onDone();
                } });
            }}>Undo</button>}
            <AdminErrors errors={form.errors} />
        </div>
    );
}

export function ToastProvider({ children }) {
    const { flash } = usePage().props;
    const [toast, setToast] = useState(null);

    useEffect(() => {
        const msg = flash?.success || flash?.error;
        if (msg) setToast({ message: msg, tone: flash?.error ? 'error' : 'success', undo: flash?.bookmarkUndo });
    }, [flash]);

    return (
        <FlashContext.Provider value={null}>
            {children}
            {toast && <Toast {...toast} onDone={() => setToast(null)} />}
        </FlashContext.Provider>
    );
}

/* ---------------------------------------------------------------- nav */
/**
 * Destination for a nav item, honouring the last sub-section the reader used.
 * Falls back to the item's own route when nothing has been remembered yet.
 */
function rememberedNavHref(item) {
    if (!item.rememberGroup) return route(item.href);

    try {
        const saved = window.localStorage.getItem(`dabooru.tab.${item.rememberGroup}`);
        const known = (item.rememberTabs ?? []).some(([, destination]) => destination === saved);

        return known ? route(saved) : route(item.href);
    } catch {
        return route(item.href);
    }
}

const BROWSE = ['gallery', 'discover', 'following'];
const INBOX = ['messages*', 'notifications*'];
const CREATE = ['create', 'upload*', 'comics.chapters.create'];
const LIBRARY = ['collections*', 'saved-searches*'];
const NAV = [
    { label: 'Browse', href: 'gallery', icon: 'gallery', matches: BROWSE },
    { label: 'Artists', href: 'artists.index', icon: 'user', matches: ['artists*'] },
    { label: 'Comics', href: 'comics.index', icon: 'book', matches: ['comics.index', 'comics.show', 'comics.read'] },
    { label: 'Tags & wiki', href: 'knowledge.index', icon: 'tag', matches: ['knowledge*'] },
    { label: 'Create', href: 'create', icon: 'plus', matches: CREATE, auth: true },
    { label: 'Creator Studio', shortLabel: 'Studio', href: 'studio.index', icon: 'upload', matches: ['studio*'], auth: true },
    { label: 'Inbox', href: 'messages.index', icon: 'chat', matches: INBOX, auth: true },
    { label: 'Tip', href: 'tip', icon: 'tipJar', matches: ['tip'] },
    { label: 'Report a bug', shortLabel: 'Bug', href: 'bug.index', icon: 'flag', matches: ['bug*'] },
    { label: 'Library', href: 'collections.index', icon: 'save', matches: LIBRARY, auth: true, rememberGroup: 'library-sections', rememberTabs: [['Collections', 'collections.index'], ['Saved Searches', 'saved-searches.index']] },
];

function accountShortcuts(user) {
    return [
        { label: 'View profile', href: route('profile.show', user.username), icon: 'user' },
        { label: 'Edit profile', href: `${route('settings.edit')}#profile`, icon: 'camera' },
        { label: 'Saved works', href: route('collections.index'), icon: 'save' },
        { label: 'Creator Studio', href: route('studio.index'), icon: 'upload' },
        { label: 'Settings', href: route('settings.edit'), icon: 'settings' },
    ];
}

function Rail({ admin = false }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const staffLinks = adminLinks(user);

    return (
        <aside aria-label={admin ? 'Admin navigation' : 'Site navigation'} className="sticky top-0 z-20 hidden h-screen flex-col gap-1 overflow-y-auto border-r border-ink-line bg-ink-bg px-2.5 pb-3.5 pt-5 lg:flex lg:px-3.5">
            <Link href={route(admin ? staffLinks[0]?.href ?? 'gallery' : 'gallery')} aria-label={admin ? 'BooruHub admin' : 'BooruHub'} className="mb-4 flex items-center gap-2.5 px-2 font-extrabold tracking-[-0.02em] xl:text-xl">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-[9px] bg-gradient-to-br from-ink-brand to-ink-pink text-[15px] font-black text-[#1a1017]">
                    D
                </span>
                <span className="hidden xl:inline">{admin ? 'BooruHub admin' : 'BooruHub'}</span>
            </Link>

            {(admin ? staffLinks : NAV).map((item) => {
                if (item.auth && !user) return null;
                const active = admin ? route().current(item.route) : item.matches.some((match) => route().current(match));
                const href = rememberedNavHref(item);
                const badge = item.label === 'Inbox' ? (auth?.unread_messages ?? 0) + (auth?.unread_notifications ?? 0) : 0;

                return (
                    <Link
                        key={item.label}
                        href={href}
                        aria-label={item.label}
                        title={item.label}
                        aria-current={active ? 'page' : undefined}
                        className={`relative flex min-h-[44px] items-center gap-3 rounded-[11px] px-3 py-2.5 text-[15.5px] font-semibold justify-start ${
                            active ? 'bg-ink-raised text-ink-brand' : 'text-ink-text hover:bg-ink-raised'
                        }`}
                    >
                        <Icon name={item.icon} className="h-[22px] w-[22px] shrink-0 opacity-90" />
                        <span className="min-w-0 text-sm xl:hidden">{item.shortLabel ?? item.label}</span>
                        <span className="hidden min-w-0 xl:inline xl:text-base">{item.label}</span>
                        {badge > 0 && (
                            <span className="absolute right-0 top-1 rounded-full bg-ink-pink px-1.5 py-0.5 text-[11px] font-extrabold text-white xl:static xl:ml-auto">
                                {badge > 99 ? '99+' : badge}
                            </span>
                        )}
                    </Link>
                );
            })}

            {admin ? (
                <Link href={route('gallery')} aria-label="Back to site" title="Back to site" className="btn-primary mt-3 xl:mt-4">
                    <Icon name="back" className="h-5 w-5 shrink-0" />
                    <span className="hidden xl:inline">Back to site</span>
                </Link>
            ) : !user ? (
                <Link href={route('login')} className="btn-primary mt-3 xl:mt-4">
                    Log in
                </Link>
            ) : null}

            {!admin && staffLinks.length > 0 && (
                <Link
                    href={route(staffLinks[0].href)}
                    aria-label="Admin panel"
                    className="relative flex min-h-[44px] items-center gap-3 rounded-[11px] px-3 py-2.5 text-[15.5px] font-semibold justify-center text-[#ff98a9] hover:bg-ink-raised xl:justify-start"
                >
                    <Icon name="flag" className="h-[22px] w-[22px] shrink-0" />
                    <span className="hidden xl:inline">Admin panel</span>
                </Link>
            )}

            <div className="mt-auto">
                {!admin && <DemoSwitcher inline />}
                {user ? (
                    <Menu>
                        <MenuButton
                            type="button"
                            aria-label="Open account menu"
                            title={`Account menu for @${user.username}`}
                            className="flex min-h-11 w-full items-center gap-2.5 rounded-xl p-2 text-left hover:bg-ink-raised focus-visible:outline focus-visible:outline-2 focus-visible:outline-ink-brand"
                        >
                            <img src={user.avatar} alt="" className="h-[38px] w-[38px] shrink-0 rounded-full object-cover" />
                            <span className="hidden min-w-0 flex-1 xl:block">
                                <span className="block truncate text-sm font-bold">{user.name}</span>
                                <span className="block truncate text-[12.5px] text-ink-muted">{admin ? (user.is_admin ? 'Administrator' : user.staff_permissions?.join(', ')) : `@${user.username}`}</span>
                            </span>
                            <Icon name="more" className="hidden h-4 w-4 shrink-0 text-ink-muted xl:block" />
                        </MenuButton>
                        <MenuItems
                            anchor={{ to: 'top start', gap: 8, padding: 8 }}
                            portal
                            transition
                            className="z-[80] w-[min(288px,calc(100vw-1rem))] origin-bottom-left rounded-2xl border border-ink-line bg-ink-surface p-2 text-ink-text shadow-2xl outline-none transition duration-100 ease-out data-[closed]:translate-y-1 data-[closed]:opacity-0"
                        >
                            <div className="flex items-center gap-3 border-b border-ink-line px-2.5 pb-3 pt-1">
                                <img src={user.avatar} alt="" className="h-10 w-10 shrink-0 rounded-full object-cover" />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-bold">{user.name}</p>
                                    <p className="truncate text-xs text-ink-muted">@{user.username}</p>
                                </div>
                            </div>
                            <div className="py-1">
                                {accountShortcuts(user).map((item) => (
                                    <MenuItem key={item.label}>
                                        {({ focus }) => (
                                            <Link href={item.href} className={`flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium ${focus ? 'bg-ink-raised text-ink-text' : 'text-ink-muted'}`}>
                                                <Icon name={item.icon} className="h-[18px] w-[18px] shrink-0" />
                                                <span>{item.label}</span>
                                            </Link>
                                        )}
                                    </MenuItem>
                                ))}
                            </div>
                            <div className="border-t border-ink-line pt-1">
                                <MenuItem>
                                    {({ focus }) => (
                                        <Link href={route('logout')} method="post" as="button" className={`flex min-h-11 w-full items-center rounded-xl px-3 py-2 text-left text-sm font-semibold ${focus ? 'bg-ink-raised text-[#ff98a9]' : 'text-[#ff98a9]'}`}>
                                            Log out
                                        </Link>
                                    )}
                                </MenuItem>
                            </div>
                        </MenuItems>
                    </Menu>
                ) : (
                    <Link href={route('register')} className="btn-ghost w-full text-[13px]">
                        Create account
                    </Link>
                )}
            </div>
        </aside>
    );
}

function BottomNav({ menuOpen, setMenuOpen }) {
    const { auth } = usePage().props;
    const { url } = usePage();
    const user = auth?.user;
    useEffect(() => setMenuOpen(false), [url, user?.id]);
    useEffect(() => {
        const desktop = window.matchMedia('(min-width: 1024px)');
        const closeOnDesktop = () => { if (desktop.matches) setMenuOpen(false); };
        if (desktop.addEventListener) {
            desktop.addEventListener('change', closeOnDesktop);
            return () => desktop.removeEventListener('change', closeOnDesktop);
        }
        desktop.addListener(closeOnDesktop);
        return () => desktop.removeListener(closeOnDesktop);
    }, []);
    const unread = (auth?.unread_messages ?? 0) + (auth?.unread_notifications ?? 0);
    // Signed-out visitors get one honest invitation instead of three links that
    // all silently redirect to the login form.
    const items = user ? [
        { label: 'Browse', icon: 'gallery', href: route('gallery'), matches: BROWSE },
        { label: 'Comics', icon: 'book', href: route('comics.index'), matches: ['comics.index', 'comics.show', 'comics.read'] },
        { label: 'Create', icon: 'plus', href: route('create'), matches: CREATE },
        { label: 'Inbox', icon: 'chat', href: route('messages.index'), matches: INBOX, badge: unread },
        { label: 'You', icon: 'user', menu: true, matches: ['profile*', 'settings*'] },
    ] : [
        { label: 'Browse', icon: 'gallery', href: route('gallery'), matches: BROWSE },
        { label: 'Artists', icon: 'user', href: route('artists.index'), matches: ['artists*'] },
        { label: 'Comics', icon: 'book', href: route('comics.index'), matches: ['comics.index', 'comics.show', 'comics.read'] },
        { label: 'Log in', icon: 'chat', href: route('login'), matches: ['login'] },
        { label: 'Join', icon: 'plus', href: route('register'), matches: ['register'] },
    ];
    const dockPages = new Set(items.map((item) => item.label));
    const morePages = NAV
        .filter((item) => (!item.auth || user) && !dockPages.has(item.label))
        .map((item) => ({ ...item, href: rememberedNavHref(item) }));
    if (user && adminLinks(user).length > 0) {
        morePages.push({ label: 'Admin panel', icon: 'flag', href: route(adminLinks(user)[0].href), matches: ['admin*'] });
    }
    const accountItems = user ? [
        { label: 'Notifications', href: route('notifications.index'), icon: 'bell', badge: auth?.unread_notifications },
        { label: 'Settings', href: route('settings.edit'), icon: 'settings' },
        { label: 'Log out', href: route('logout'), icon: 'logout', method: 'post' },
    ] : [
        { label: 'Log in', href: route('login'), icon: 'user' },
        { label: 'Create account', href: route('register'), icon: 'plus' },
    ];

    return (
        <>
        <nav className="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-ink-line bg-ink-bg/95 pb-safe backdrop-blur lg:hidden" aria-label="Main">
            {items.map((item) => {
                const active = item.matches.some((match) => route().current(match));
                const className = `relative flex min-h-[52px] flex-col items-center justify-center gap-0.5 py-1.5 text-[10.5px] ${active ? 'text-ink-brand' : 'text-ink-muted'}`;
                if (item.menu) {
                    return (
                        <button key={item.label} type="button" aria-label="You" aria-haspopup="dialog" aria-expanded={menuOpen} onClick={() => setMenuOpen(true)} className={className}>
                            {user ? <img src={user.avatar} alt="" className="h-6 w-6 rounded-full object-cover" /> : <Icon name="user" className="h-[22px] w-[22px]" />}
                            <span>You</span>
                        </button>
                    );
                }
                return (
                    <Link
                        key={item.label}
                        href={item.href}
                        aria-label={item.label}
                        aria-current={active ? 'page' : undefined}
                        className={className}
                    >
                        <Icon name={item.icon} className="h-[22px] w-[22px]" />
                        <span>{item.shortLabel ?? item.label}</span>
                        {item.badge > 0 && (
                            <span className="absolute right-[calc(50%-22px)] top-0.5 rounded-full bg-ink-pink px-1 text-[9.5px] font-extrabold text-white" aria-label={`${item.badge} unread`}>
                                {item.badge > 99 ? '99+' : item.badge}
                            </span>
                        )}
                    </Link>
                );
            })}
        </nav>
        <Dialog open={menuOpen} onClose={() => setMenuOpen(false)} onKeyDown={(event) => {
            if (event.key === 'Escape') setMenuOpen(false);
        }} className="fixed inset-0 z-[100]">
            <div className="fixed inset-0 bg-black/70" aria-hidden="true" />
            <div className="fixed inset-0 flex items-end justify-center p-3 pb-safe sm:items-center">
                <DialogPanel className="max-h-[85dvh] w-full max-w-md overflow-y-auto rounded-2xl border border-ink-line bg-ink-bg p-4 shadow-2xl">
                    <div className="mb-3 flex items-center gap-3">
                        {user && <img src={user.avatar} alt="" className="h-10 w-10 rounded-full object-cover" />}
                        <div className="min-w-0 flex-1">
                            <DialogTitle className="truncate font-bold">{user?.name ?? 'Account'}</DialogTitle>
                            {user && <p className="truncate text-sm text-ink-muted">@{user.username}</p>}
                        </div>
                        <button type="button" onClick={() => setMenuOpen(false)} className="btn-ghost min-h-11 px-3">Close</button>
                    </div>
                    {user && <Link href={route('profile.show', user.username)} onClick={() => setMenuOpen(false)} className="mb-3 flex min-h-11 items-center justify-center gap-2 rounded-xl bg-ink-brand px-3 py-2 text-sm font-bold text-[#180f18]">
                        <Icon name="user" className="h-[18px] w-[18px]" /> View profile
                    </Link>}
                    <nav aria-label="Account" className="flex flex-col gap-1">
                        {accountItems.map((item) => <Link key={item.label} href={item.href} method={item.method} as={item.method ? 'button' : undefined} onClick={() => setMenuOpen(false)} className="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-left text-sm hover:bg-ink-raised">
                            <span className="flex items-center gap-3"><Icon name={item.icon} className="h-[18px] w-[18px] shrink-0 text-ink-muted" />{item.label}</span>
                            {item.badge > 0 && <span className="rounded-full bg-ink-pink px-2 text-xs font-bold text-white">{item.badge > 99 ? '99+' : item.badge}</span>}
                        </Link>)}
                    </nav>
                    {morePages.length > 0 && <div className="mt-3 border-t border-ink-line pt-2">
                        <h3 className="px-3 pb-1 text-xs font-semibold text-ink-faint">More pages</h3>
                        <nav aria-label="More pages" className="flex flex-col gap-1">
                            {morePages.map((item) => <Link key={item.label} href={item.href} aria-current={item.matches?.some((match) => route().current(match)) ? 'page' : undefined} onClick={() => setMenuOpen(false)} className="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-sm hover:bg-ink-raised">
                                <Icon name={item.icon} className="h-[18px] w-[18px] shrink-0 text-ink-muted" />{item.label}
                            </Link>)}
                        </nav>
                    </div>}
                </DialogPanel>
            </div>
        </Dialog>
        </>
    );
}

/* ---------------------------------------------------------------- right rail */
function RightRail() {
    const { discoveryRail = {} } = usePage().props;
    const { risingArtists = [], popularWorks = [] } = discoveryRail;

    return (
        <aside className="sticky top-0 hidden h-screen flex-col gap-4 overflow-y-auto border-l border-ink-line px-4 py-5 xl:flex">
            <AdSlot placement="sidebar" fallback={(reason) => (
                <div className="rounded-xl border border-dashed border-ink-line bg-ink-raised/40 p-3">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-ink-faint">Sponsored placement</p>
                    {reason === 'image-error' && <p role="status" className="mt-1 text-xs text-ink-muted">The sponsor image could not be loaded.</p>}
                    <p className="mt-1 text-xs leading-relaxed text-ink-muted">Artist and work boosts are planned, but paid promotion is not available yet.</p>
                </div>
            )} />

            {risingArtists.length > 0 && (
                <div className="panel">
                    <h3 className="card-title">Rising artists</h3>
                    {risingArtists.map((artist) => (
                        <div key={artist.id} className="flex items-center gap-2.5 py-2">
                            <Link href={route('profile.show', artist.username)}>
                                <img src={artist.avatar} alt="" className="h-[34px] w-[34px] rounded-full object-cover" />
                            </Link>
                            <Link href={route('profile.show', artist.username)} className="min-w-0">
                                <p className="truncate text-[13.5px] font-semibold leading-tight">{artist.name}</p>
                                <p className="truncate text-xs text-ink-muted">{artist.recent_posts_count} new {artist.recent_posts_count === 1 ? 'work' : 'works'} this month</p>
                            </Link>
                        </div>
                    ))}
                    <Link href={route('artists.index')} className="mt-2 inline-flex text-xs font-semibold text-ink-brand hover:underline">Browse artists</Link>
                </div>
            )}

            {popularWorks.length > 0 && (
                <section aria-label="Popular works" className="panel">
                    <h3 className="card-title">Popular works</h3>
                    <div className="space-y-3">
                        {popularWorks.map((work) => (
                            <WorkLink key={work.id} href={work.url} className="flex min-w-0 items-center gap-2.5 rounded-lg hover:bg-ink-raised">
                                <span className="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-ink-raised">
                                    {work.media?.[0] && <MediaThumbnail media={work.media[0]} className="h-full w-full object-cover" />}
                                </span>
                                <span className="min-w-0">
                                    <span className="block truncate text-[13px] font-semibold">{work.title || 'Untitled work'}</span>
                                    <span className="block truncate text-xs text-ink-muted">{workArtistName(work)}</span>
                                    <span className="block text-[11px] text-ink-faint">{work.week_likes ? `${work.week_likes} likes this week` : `${work.lifetime_likes} total likes`}</span>
                                </span>
                            </WorkLink>
                        ))}
                    </div>
                    <Link href={route('discover')} className="mt-3 inline-flex text-xs font-semibold text-ink-brand hover:underline">Explore more</Link>
                </section>
            )}

            <div className="rounded-xl border border-ink-line bg-ink-raised/50 p-3 text-[11.5px] leading-relaxed text-ink-faint">
                <p>Work with no artist in its file name is filed under <b className="text-ink-muted">Unsorted</b>.</p>
                <p className="mt-2">No ads inside the comic reader.</p>
            </div>
        </aside>
    );
}

/* ---------------------------------------------------------------- demo switcher */
/* Local development only: the server only sends demoAccounts when the
   demo-login route exists (it does not exist in production). */
function DemoSwitcher({ inline = false }) {
    const { demoAccounts, auth } = usePage().props;
    const [open, setOpen] = useState(false);

    if (!demoAccounts?.length) return null;

    const current = auth?.user?.username;

    // Inline mode sits in the desktop rail footer so it never covers the user link;
    // the floating button is only for md widths where the rail is hidden.
    return (
        <div className={inline ? 'relative mb-1 hidden lg:block' : 'fixed bottom-4 left-3 z-50 hidden md:block lg:hidden'}>
            {open && (
                <div className={`mb-2 rounded-2xl border border-ink-line bg-ink-surface p-2 shadow-2xl ${inline ? 'absolute inset-x-0 bottom-full z-50' : 'w-[248px]'}`}>
                    <p className="px-2 pb-1 pt-1 text-[10px] font-extrabold uppercase tracking-[0.12em] text-ink-faint">
                        Sign in as
                    </p>
                    {demoAccounts.map((account) => (
                        <a
                            key={account.username}
                            href={account.url}
                            className={`flex items-center gap-2.5 rounded-xl px-2 py-2 hover:bg-ink-raised ${
                                current === account.username ? 'bg-ink-raised' : ''
                            }`}
                        >
                            <img src={account.avatar} alt="" className="h-8 w-8 shrink-0 rounded-full bg-ink-raised object-cover" />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-[13px] font-bold">{account.name}</span>
                                <span className="block truncate text-[11px] text-ink-muted">{account.role}</span>
                            </span>
                            {current === account.username && <span className="text-[11px] text-ink-brand">here</span>}
                        </a>
                    ))}
                    <a
                        href={route('login')}
                        className="mt-1 block rounded-xl px-2 py-2 text-[12px] font-semibold text-ink-muted hover:bg-ink-raised hover:text-ink-text"
                    >
                        Use the login form instead
                    </a>
                </div>
            )}
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                aria-label="Switch demo account"
                className={`flex items-center gap-2 rounded-full border border-ink-line bg-ink-surface px-3 py-2 text-[12px] font-bold shadow-xl hover:bg-ink-raised ${inline ? 'w-full justify-center shadow-none' : ''}`}
            >
                <span className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-ink-brand text-[10px] font-black text-[#1c1017]">
                    ⇄
                </span>
                <span className={inline ? 'hidden xl:inline' : ''}>Accounts</span>
            </button>
        </div>
    );
}

/* ---------------------------------------------------------------- footer */
/**
 * The legal footer. Kept as its own export so pages that manage their own
 * viewport (the comic reader, a chat thread) can still end with it.
 */
export function LegalFooter({ className = '', compact = false }) {
    return (
        <footer className={`border-t border-ink-line px-5 text-[12px] text-ink-faint ${compact ? 'py-2' : 'py-4'} ${className}`}>
            <nav className="flex flex-wrap gap-x-4 gap-y-1" aria-label="Legal">
                <Link href={route('legal.terms')} className="hover:text-ink-text">Terms</Link>
                <Link href={route('legal.privacy')} className="hover:text-ink-text">Privacy</Link>
                <Link href={route('legal.policy')} className="hover:text-ink-text">Content policy</Link>
                <Link href={route('dmca')} className="hover:text-ink-text">Copyright / DMCA</Link>
                <span className="w-full md:ml-auto md:w-auto">18+ only. Work is rated by its artist.</span>
            </nav>
        </footer>
    );
}

/* ---------------------------------------------------------------- layout */
export default function AppLayout({ children, rightRail = true, bare = false, admin = false, footer = true }) {
    const shellName = rightRail ? 'shell-full' : 'shell-solo';
    const [adminRailOpen, setAdminRailOpen] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <ToastProvider>
            <div className={`mx-auto grid min-h-screen max-w-shell grid-cols-1 items-start gap-0 ${bare ? '' : shellName}`}>
                {!bare && <Rail admin={admin} />}
                <main
                    className={`min-h-screen border-r border-ink-line ${!footer ? 'pb-0 lg:pb-0' : admin ? 'pb-6 lg:pb-16' : 'pb-20 md:min-w-0 lg:pb-16'} ${
                        rightRail ? '' : 'border-r-0'
                    }`}
                >
                    <NavigationToggleContext.Provider value={admin && !bare ? {
                        open: () => setAdminRailOpen(true),
                        expanded: adminRailOpen,
                    } : null}>
                        {!bare && !admin && <SiteAnnouncement />}
                        {children}
                    </NavigationToggleContext.Provider>
                </main>
                {!bare && rightRail && <RightRail />}
                {!bare && !admin && <BottomNav menuOpen={menuOpen} setMenuOpen={setMenuOpen} />}
                {!bare && admin && <AdminMobileRail open={adminRailOpen} onClose={() => setAdminRailOpen(false)} />}
                {!bare && !admin && <DemoSwitcher />}
                {footer && (
                    <LegalFooter
                        className="col-span-1 pb-24 md:pb-4 lg:col-start-2 lg:col-span-1"
                    />
                )}
            </div>
        </ToastProvider>
    );
}

/* ---------------------------------------------------------------- top bar */
export function TopBar({ title, subtitle, back = null, backLabel = 'Back', onBack, children, tabs = null, actions = null, siteActions = true, search = true, searchScope = 'gallery', hideOnScroll = false, exitHref = null, exitLabel = 'Back to site' }) {
    const { props } = usePage();
    const navigation = useContext(NavigationToggleContext);
    const [hidden, setHidden] = useState(false);
    const unreadNotifications = props.auth?.unread_notifications ?? 0;

    useEffect(() => {
        if (!hideOnScroll) {
            setHidden(false);
            return;
        }
        let previous = Math.max(0, window.scrollY);
        const onScroll = () => {
            const current = Math.max(0, Math.min(window.scrollY, document.documentElement.scrollHeight - window.innerHeight));
            const difference = current - previous;
            if (current <= 16) {
                setHidden(false);
            } else if (Math.abs(difference) < 8) {
                return;
            } else {
                setHidden(difference > 0);
            }
            previous = current;
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, [hideOnScroll]);

    return (
        <div onFocusCapture={() => setHidden(false)}
            className={`sticky top-0 z-[15] border-b border-ink-line bg-ink-bg/90 backdrop-blur ${hideOnScroll ? 'transition-transform duration-200 motion-reduce:transition-none' : ''} ${hidden ? '-translate-y-full' : ''}`}>
            <div className="flex min-h-[56px] items-center gap-3 px-4 pt-3 md:px-5">
                {navigation && <button type="button" onClick={navigation.open}
                    aria-label="Open admin navigation"
                    aria-expanded={navigation.expanded}
                    className="btn-icon-flat -ml-2 shrink-0 lg:hidden">
                    <Icon name="following" />
                </button>}
                {back && (
                    <Link href={back} onClick={onBack} aria-label={backLabel} title={backLabel} className="btn-icon-flat -ml-2 shrink-0">
                        <Icon name="back" />
                    </Link>
                )}
                <h1 className="min-w-0 truncate text-[19px] font-bold tracking-[-0.02em] md:text-[21px]">
                    {title}
                    {subtitle && <span className="ml-2 hidden text-sm font-medium text-ink-muted md:inline">{subtitle}</span>}
                </h1>
                <div className="ml-auto flex min-w-0 shrink-0 items-center gap-2">
                    {actions && <div className="flex min-w-0 flex-wrap items-center justify-end gap-2">{actions}</div>}
                    {props.auth?.user && !navigation && <Link
                        href={route('notifications.index')}
                        aria-label={unreadNotifications > 0 ? `Notifications, ${unreadNotifications} unread` : 'Notifications'}
                        title="Notifications"
                        className="relative grid h-10 w-10 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-ink-raised hover:text-ink-text"
                    >
                        <Icon name="bell" className="h-5 w-5" />
                        {unreadNotifications > 0 && <span className="absolute right-0 top-0 min-w-[17px] rounded-full bg-ink-pink px-1 text-center text-[10px] font-extrabold leading-[17px] text-white">
                            {unreadNotifications > 99 ? '99+' : unreadNotifications}
                        </span>}
                    </Link>}
                    {siteActions && search && <div className="hidden w-[min(36vw,300px)] lg:block">
                        <SearchBox scope={searchScope} initial={searchScope === 'comics' ? props.searchQuery ?? '' : searchScope === 'knowledge' ? props.filters?.q ?? '' : ''} />
                    </div>}
                    {!siteActions && <Link href={exitHref ?? route('gallery')} className="btn-ghost min-h-11 text-[12px]">
                        <Icon name="back" className="h-4 w-4 shrink-0" />
                        {exitLabel}
                    </Link>}
                </div>
            </div>
            {tabs}
        </div>
    );
}

/* ---------------------------------------------------------------- rating badge */
export function RatingBadge({ rating, label, className = '' }) {
    return <span className={`${RATING_CLASS[rating] ?? 'rate-badge-sfw'} ${className}`}>{label ?? rating}</span>;
}
