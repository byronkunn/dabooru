import { InboxTabs } from '@/Components/DestinationTabs';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';

const ICONS = { like: '♥', comment: '💬', follow: '＋', chapter: '📖', urgent_report: '⚑' };
const TEXT = {
    like: 'liked your work',
    comment: 'commented on your work',
    follow: 'started following you',
    chapter: 'published a new chapter',
};

export default function NotificationsIndex({ notifications, tab }) {
    const tabs = [
        ['all', 'All'],
        ['likes', 'Likes'],
        ['comments', 'Comments'],
        ['follows', 'Follows'],
    ];

    const go = (key) => router.get(route('notifications.index'), { tab: key }, { preserveScroll: true, preserveState: true });

    return (
        <AppLayout rightRail={false}>
            <Head title="Activity" />
            <TopBar
                title="Inbox"
                actions={
                    <button type="button" onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })} className="btn-ghost h-9 px-3 text-[12.5px]">
                        Mark all read
                    </button>
                }
                tabs={<>
                    <InboxTabs />
                    <div className="scrollbar-none flex gap-1.5 overflow-x-auto px-4 py-3 md:px-5" role="tablist">
                        {tabs.map(([key, label]) => (
                            <button key={key} type="button" role="tab" aria-selected={tab === key} onClick={() => go(key)} className="chip">
                                {label}
                            </button>
                        ))}
                    </div>
                </>}
            />

            {notifications.length === 0 ? (
                <div className="grid place-items-center gap-2 px-6 py-24 text-center sm:py-32">
                    <div className="grid h-14 w-14 place-items-center rounded-full bg-ink-raised">
                        <Icon name="bell" className="h-6 w-6 text-ink-muted" />
                    </div>
                    <p className="text-[15px] text-ink-muted">Nothing here yet.</p>
                </div>
            ) : (
                notifications.map((n) => (
                    <div key={n.id} className={`flex items-center gap-3 border-b border-ink-line px-3.5 py-3.5 md:px-5 ${n.read ? '' : 'bg-ink-brand/5'}`}>
                        <div className="relative h-11 w-11 shrink-0">
                            {n.actor ? (
                                <img src={n.actor.avatar} alt="" className="h-11 w-11 rounded-full object-cover" />
                            ) : (
                                <span role="img" aria-label="Community" className="grid h-11 w-11 place-items-center rounded-full bg-ink-raised text-ink-brand">
                                    <Icon name="bell" className="h-6 w-6" />
                                </span>
                            )}
                            <span className="absolute -bottom-1 -right-1 grid h-5 w-5 place-items-center rounded-full border border-ink-line bg-ink-bg text-[11px]">
                                {ICONS[n.type] ?? '•'}
                            </span>
                        </div>
                        <div className="min-w-0 flex-1 text-[14.5px] leading-snug">
                            {n.actor ? (
                                <Link href={route('profile.show', n.actor.username)} className="font-bold hover:underline">{n.actor.name}</Link>
                            ) : <span>Community</span>}{' '}
                            {n.type === 'urgent_report' ? (
                                <Link href={route('admin.reports', { report: n.data.report_id })} className="font-bold text-ink-expl hover:underline">Urgent report awaiting review</Link>
                            ) : n.type === 'knowledge' ? (
                                <Link href={n.data.url} className="text-ink-brand hover:underline">{n.data.message}</Link>
                            ) : n.type === 'comment' && n.data?.reply ? 'replied to your comment' : (TEXT[n.type] ?? n.type)}
                            {n.data?.post_id && (
                                <Link href={`${route('posts.show', n.data.post_id)}${n.type === 'comment' ? '#comments' : ''}`} className="ml-1 font-bold hover:underline">
                                    #{n.data.post_id}
                                </Link>
                            )}
                            <p className="mt-0.5 text-[12.5px] text-ink-muted md:hidden">{n.created_at_human}</p>
                        </div>
                        <span className="hidden shrink-0 text-xs text-ink-faint md:inline">{n.created_at_human}</span>
                    </div>
                ))
            )}
        </AppLayout>
    );
}
