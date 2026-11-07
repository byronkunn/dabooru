import { Head, router } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { AdminTabs } from '@/Components/AdminTabs';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

const FILTERS = [
    ['all', 'Everyone'],
    ['banned', 'Banned'],
    ['unsorted', 'Unsorted bucket'],
];

export default function AdminUsers({ users, q, filter }) {
    const [search, setSearch] = useState(q ?? '');
    const [reason, setReason] = useState('');

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('admin.users'), { q: search, filter }, { preserveState: true, replace: true });
    };

    const ban = (user) => {
        if (!reason.trim()) {
            window.alert('Add a reason first — it is recorded in the moderation log.');
            return;
        }
        router.post(route('admin.users.ban', user.id), { reason }, { preserveScroll: true, onSuccess: () => setReason('') });
    };

    const unban = (user) => router.post(route('admin.users.unban', user.id), {}, { preserveScroll: true });

    return (
        <AppLayout rightRail={false}>
            <Head title="Users" />
            <TopBar title="Moderation" subtitle="Users" />
            <AdminTabs />

            <div className="flex flex-wrap gap-2 px-3.5 py-3 md:px-5">
                {FILTERS.map(([key, label]) => (
                    <Link key={key} href={route('admin.users', { filter: key, q })} className={filter === key ? 'chip is-active' : 'chip'}>
                        {label}
                    </Link>
                ))}
            </div>

            <form onSubmit={submitSearch} className="px-3.5 pb-3 md:px-5">
                <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search name, handle or email" className="field-input" aria-label="Search users" />
            </form>

            <div className="px-3.5 pb-3 md:px-5">
                <input
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    placeholder="Ban reason (required, recorded in the log)"
                    className="field-input"
                    maxLength={500}
                    aria-label="Ban reason"
                />
            </div>

            <ul>
                {users.map((user) => (
                    <li key={user.id} className="flex flex-wrap items-center gap-3 border-b border-ink-line px-3.5 py-3 md:px-5">
                        <img src={user.avatar} alt="" className="h-10 w-10 shrink-0 rounded-full bg-ink-raised object-cover" />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-[14px] font-bold">
                                {user.name}{' '}
                                <span className="font-medium text-ink-muted">@{user.username}</span>
                                {user.is_admin && <span className="ml-1.5 rounded bg-ink-brand/20 px-1.5 py-0.5 text-[10px] font-extrabold uppercase text-ink-brand">admin</span>}
                                {user.is_unsorted && <span className="ml-1.5 rounded bg-ink-raised px-1.5 py-0.5 text-[10px] font-extrabold uppercase text-ink-faint">bucket</span>}
                            </p>
                            <p className="truncate text-[12px] text-ink-faint">
                                {user.email} · {user.posts} posts
                                {user.banned_at && ` · banned: ${user.banned_reason ?? 'no reason given'}`}
                            </p>
                        </div>
                        {user.banned_at ? (
                            <button type="button" onClick={() => unban(user)} className="btn-ghost min-h-[36px] text-[12.5px]">
                                Unban
                            </button>
                        ) : (
                            !user.is_admin && (
                                <button type="button" onClick={() => ban(user)} className="btn min-h-[36px] rounded-full bg-ink-expl px-3.5 text-[12.5px] text-white">
                                    Ban
                                </button>
                            )
                        )}
                    </li>
                ))}
            </ul>
            {users.length === 0 && <p className="px-4 py-12 text-center text-sm text-ink-muted">No accounts match.</p>}
        </AppLayout>
    );
}
