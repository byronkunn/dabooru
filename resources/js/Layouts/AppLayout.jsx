import { Link, usePage } from '@inertiajs/react';
import { createContext, useContext, useEffect, useState } from 'react';
import { Icon } from '@/Components/Icons';
import { RATING_CLASS } from '@/Lib/format';

const FlashContext = createContext(null);

/* ---------------------------------------------------------------- toast */
function Toast({ message, tone, onDone }) {
    useEffect(() => {
        const t = setTimeout(onDone, 2400);
        return () => clearTimeout(t);
    }, [message, onDone]);

    return (
        <div
            role="status"
            className="fixed bottom-24 left-1/2 z-[200] -translate-x-1/2 rounded-xl border border-[#3d3447] bg-[#241f2b] px-5 py-3 text-[13.5px] shadow-2xl md:bottom-6"
        >
            {message}
        </div>
    );
}

export function ToastProvider({ children }) {
    const { flash } = usePage().props;
    const [toast, setToast] = useState(null);

    useEffect(() => {
        const msg = flash?.success || flash?.error;
        if (msg) setToast({ message: msg, tone: flash?.error ? 'error' : 'success' });
    }, [flash]);

    return (
        <FlashContext.Provider value={null}>
            {children}
            {toast && <Toast {...toast} onDone={() => setToast(null)} />}
        </FlashContext.Provider>
    );
}

/* ---------------------------------------------------------------- nav */
const NAV = [
    { label: 'Gallery', href: 'gallery', icon: 'gallery', route: 'gallery' },
    { label: 'Following', href: 'following', icon: 'following', route: 'following', auth: true },
    { label: 'Messages', href: 'messages.index', icon: 'chat', route: 'messages', auth: true, badge: 'unread_messages' },
    { label: 'Notifications', href: 'notifications.index', icon: 'bell', route: 'notifications', auth: true, badge: 'unread_notifications' },
    { label: 'Comics', href: 'comics.index', icon: 'bookmark', route: 'comics' },
    { label: 'Profile', href: 'profile.show', icon: 'user', route: 'profile', auth: true },
];

function Rail() {
    const { auth, ageConfirmed } = usePage().props;
    const user = auth?.user;

    return (
        <aside className="sticky top-0 z-20 hidden h-screen flex-col gap-1 overflow-y-auto border-r border-ink-line bg-ink-bg px-2.5 pb-3.5 pt-5 md:flex lg:px-3.5">
            <Link href={route('gallery')} className="mb-4 flex items-center gap-2.5 px-2 font-extrabold tracking-[-0.02em] xl:text-xl">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-[9px] bg-gradient-to-br from-ink-brand to-ink-pink text-[15px] font-black text-[#1a1017]">
                    I
                </span>
                <span className="hidden xl:inline">Inkwell</span>
            </Link>

            {NAV.map((item) => {
                if (item.auth && !user) return null;
                const active = item.route === 'gallery'
                    ? route().current('gallery')
                    : route().current(`${item.route}*`);
                const href = item.href === 'profile.show'
                    ? route('profile.show', user?.username ?? 'me')
                    : route(item.href);
                const badge = item.badge ? auth?.[item.badge] : 0;

                return (
                    <Link
                        key={item.label}
                        href={href}
                        aria-label={item.label}
                        aria-current={active ? 'page' : undefined}
                        className={`relative flex min-h-[44px] items-center gap-3 rounded-[11px] px-3 py-2.5 text-[15.5px] font-semibold justify-center xl:justify-start ${
                            active ? 'bg-ink-raised text-ink-brand' : 'text-ink-text hover:bg-ink-raised'
                        }`}
                    >
                        <Icon name={item.icon} className="h-[22px] w-[22px] shrink-0 opacity-90" />
                        <span className="hidden xl:inline">{item.label}</span>
                        {badge > 0 && (
                            <span className="absolute right-0 top-1 rounded-full bg-ink-pink px-1.5 py-0.5 text-[11px] font-extrabold text-white xl:static xl:ml-auto">
                                {badge > 99 ? '99+' : badge}
                            </span>
                        )}
                    </Link>
                );
            })}

            {user ? (
                <Link href={route('upload.create')} className="btn-primary mt-3 xl:mt-4">
                    <Icon name="plus" className="h-5 w-5" />
                    <span className="hidden xl:inline">Upload work</span>
                </Link>
            ) : (
                <Link href={route('login')} className="btn-primary mt-3 xl:mt-4">
                    Log in
                </Link>
            )}

            <div className="mt-auto">
                {user ? (
                    <Link href={route('profile.show', user.username)} className="flex items-center gap-2.5 rounded-xl p-2 hover:bg-ink-raised">
                        <img src={user.avatar} alt="" className="h-[38px] w-[38px] shrink-0 rounded-full object-cover" />
                        <span className="hidden min-w-0 xl:block">
                            <span className="block truncate text-sm font-bold">{user.name}</span>
                            <span className="block truncate text-[12.5px] text-ink-muted">@{user.username}</span>
                        </span>
                    </Link>
                ) : (
                    <Link href={route('register')} className="btn-ghost w-full text-[13px]">
                        Create account
                    </Link>
                )}
            </div>
        </aside>
    );
}

function BottomNav() {
    const { auth } = usePage().props;
    const user = auth?.user;
    const items = [
        { label: 'Gallery', icon: 'gallery', href: route('gallery'), match: 'gallery' },
        { label: 'Following', icon: 'following', href: user ? route('following') : route('login'), match: 'following' },
        { label: 'Messages', icon: 'chat', href: user ? route('messages.index') : route('login'), match: 'messages', badge: auth?.unread_messages },
        { label: 'Notifications', icon: 'bell', href: user ? route('notifications.index') : route('login'), match: 'notifications', badge: auth?.unread_notifications },
        { label: 'Profile', icon: 'user', href: user ? route('profile.show', user.username) : route('login'), match: 'profile' },
    ];

    return (
        <nav className="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-ink-line bg-ink-bg/95 pb-safe backdrop-blur md:hidden" aria-label="Main">
            {items.map((item) => {
                const active = route().current(`${item.match}*`);
                return (
                    <Link
                        key={item.label}
                        href={item.href}
                        aria-label={item.label}
                        aria-current={active ? 'page' : undefined}
                        className={`relative flex min-h-[52px] flex-col items-center justify-center gap-0.5 py-1.5 text-[10.5px] ${
                            active ? 'text-ink-brand' : 'text-ink-muted'
                        }`}
                    >
                        <Icon name={item.icon} className="h-[22px] w-[22px]" />
                        <span>{item.label}</span>
                        {item.badge > 0 && (
                            <span className="absolute right-[calc(50%-22px)] top-0.5 rounded-full bg-ink-pink px-1 text-[9.5px] font-extrabold text-white">
                                {item.badge > 99 ? '99+' : item.badge}
                            </span>
                        )}
                    </Link>
                );
            })}
        </nav>
    );
}

/* ---------------------------------------------------------------- right rail */
function RightRail() {
    const { suggested = [], trendingTags = [], ageConfirmed } = usePage().props;

    return (
        <aside className="sticky top-0 hidden h-screen flex-col gap-4 overflow-y-auto border-l border-ink-line px-4 py-5 lg:flex">
            <div className="panel">
                <p className="mb-2 text-[10px] font-extrabold uppercase tracking-[0.1em] text-ink-faint">Ad — sidebar 300×250</p>
                <div className="grid h-[190px] place-items-center rounded-xl border border-dashed border-[#4a3c58] bg-[#141118] text-center text-xs text-ink-faint">
                    <span>
                        Ad slot <code className="text-ink-muted">sidebar</code>
                        <br />
                        <span className="opacity-70">network tag renders here</span>
                    </span>
                </div>
            </div>

            {suggested.length > 0 && (
                <div className="panel">
                    <h3 className="card-title">Artists to follow</h3>
                    {suggested.map((artist) => (
                        <div key={artist.id} className="flex items-center gap-2.5 py-2">
                            <Link href={route('profile.show', artist.username)}>
                                <img src={artist.avatar} alt="" className="h-[34px] w-[34px] rounded-full object-cover" />
                            </Link>
                            <Link href={route('profile.show', artist.username)} className="min-w-0">
                                <p className="truncate text-[13.5px] font-semibold leading-tight">{artist.name}</p>
                                <p className="truncate text-xs text-ink-muted">@{artist.username}</p>
                            </Link>
                        </div>
                    ))}
                </div>
            )}

            {trendingTags.length > 0 && (
                <div className="panel">
                    <h3 className="card-title">Trending tags</h3>
                    <div className="flex flex-wrap gap-1.5">
                        {trendingTags.map((tag) => (
                            <span key={tag} className="chip-plain">
                                {tag}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            <p className="px-1 text-[11.5px] leading-relaxed text-ink-faint">
                No ads inside the comic reader. Promoted tiles are labelled and collapse when empty.
            </p>
        </aside>
    );
}

/* ---------------------------------------------------------------- layout */
export default function AppLayout({ children, rightRail = true, bare = false }) {
    const shellName = rightRail ? 'shell-full' : 'shell-solo';

    return (
        <ToastProvider>
            <div className={`mx-auto grid min-h-screen max-w-shell grid-cols-1 items-start gap-0 ${bare ? '' : shellName}`}>
                {!bare && <Rail />}
                <main
                    className={`min-h-screen border-r border-ink-line pb-20 md:min-w-0 md:pb-16 ${
                        rightRail ? '' : 'border-r-0'
                    }`}
                >
                    {children}
                </main>
                {!bare && rightRail && <RightRail />}
                {!bare && <BottomNav />}
            </div>
        </ToastProvider>
    );
}

/* ---------------------------------------------------------------- top bar */
export function TopBar({ title, subtitle, back = null, children, tabs = null, actions = null }) {
    return (
        <div className="sticky top-0 z-[15] border-b border-ink-line bg-ink-bg/90 backdrop-blur">
            <div className="flex min-h-[56px] items-center gap-3 px-4 pt-3 md:px-5">
                {back && (
                    <Link href={back} aria-label="Back" className="btn-icon-flat -ml-2 shrink-0">
                        <Icon name="back" />
                    </Link>
                )}
                <h1 className="min-w-0 truncate text-[19px] font-bold tracking-[-0.02em] md:text-[21px]">
                    {title}
                    {subtitle && <span className="ml-2 hidden text-sm font-medium text-ink-muted md:inline">{subtitle}</span>}
                </h1>
                <div className="ml-auto flex items-center gap-2">
                    {actions}
                    <Link href={route('gallery', { q: '' })} aria-label="Search" className="btn-icon-flat md:hidden">
                        <Icon name="search" />
                    </Link>
                    <Link href={route('upload.create')} aria-label="Upload work" className="btn-primary md:hidden h-10 w-10 p-0">
                        <Icon name="plus" />
                    </Link>
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
