import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { AdminErrors, AdminPagination } from '@/Components/AdminUi';

const FILTERS = [['all', 'All accounts'], ['staff', 'Staff'], ['banned', 'Banned'], ['unsorted', 'Unsorted']];

function AccountRow({ user, roleOptions, canManageAdmins, viewer }) {
    const ban = useForm({ reason: '' });
    const roles = useForm({ roles: user.roles, ...(canManageAdmins ? { administrator: user.is_admin } : {}), reason: '' });
    const canEditRoles = user.id !== viewer.id && !user.is_owner && (!user.is_admin || canManageAdmins);

    return (
        <li className="panel space-y-3">
            <div className="flex items-start gap-3">
                <img src={user.avatar} alt="" className="h-10 w-10 shrink-0 rounded-full object-cover" />
                <div className="min-w-0 flex-1">
                    <Link href={route('profile.show', user.username)} className="break-words font-bold hover:underline">{user.name}</Link>
                    <p className="break-words text-sm text-ink-muted">@{user.username} · {user.email}</p>
                    <p className="text-xs text-ink-faint">{user.posts} works · {user.verified ? 'Verified' : 'Unverified'}{user.banned_at ? ' · Banned' : ''}</p>
                </div>
            </div>
            <div className="flex flex-wrap gap-1.5">
                {user.is_admin && <span className="chip-plain">{user.is_owner ? 'Owner' : 'Administrator'}</span>}
                {user.roles.map((role) => <span key={role} className="chip-plain capitalize">{role}</span>)}
            </div>
            {user.banned_reason && <p className="break-words text-sm text-ink-muted">{user.banned_reason}</p>}
            {canEditRoles && (
                <details>
                    <summary className="cursor-pointer text-sm font-semibold text-ink-brand">Manage roles</summary>
                    <form className="mt-3 space-y-3" onSubmit={(event) => {
                        event.preventDefault();
                        roles.patch(route('admin.users.roles', user.username), { preserveScroll: true, onSuccess: () => roles.reset('reason') });
                    }}>
                        <fieldset className="grid gap-2 sm:grid-cols-2">
                            <legend className="sr-only">Staff roles</legend>
                            {Object.entries(roleOptions).map(([key, description]) => (
                                <label key={key} className="flex items-start gap-2 rounded-lg border border-ink-line p-2 text-sm">
                                    <input type="checkbox" checked={roles.data.roles.includes(key)} onChange={(event) => roles.setData('roles', event.target.checked ? [...roles.data.roles, key] : roles.data.roles.filter((role) => role !== key))} />
                                    <span><strong className="block capitalize">{key}</strong><span className="text-xs text-ink-muted">{description}</span></span>
                                </label>
                            ))}
                            {canManageAdmins && <label className="flex items-center gap-2 text-sm font-semibold">
                                <input type="checkbox" checked={roles.data.administrator} onChange={(event) => roles.setData('administrator', event.target.checked)} />
                                Full administrator access
                            </label>}
                        </fieldset>
                        <label className="block text-sm">
                            Reason for changing access
                            <input className="field-input mt-1" value={roles.data.reason} onChange={(event) => roles.setData('reason', event.target.value)} maxLength={500} required />
                        </label>
                        <AdminErrors errors={roles.errors} />
                        <button className="btn-primary" disabled={roles.processing}>Save roles</button>
                    </form>
                </details>
            )}
            {user.banned_at ? (
                <button type="button" className="btn-ghost" disabled={ban.processing} onClick={() => ban.post(route('admin.users.unban', user.username), { preserveScroll: true })}>Unban</button>
            ) : !user.is_admin && user.id !== viewer.id && (
                <details>
                    <summary className="cursor-pointer text-sm text-ink-expl">Ban account</summary>
                    <form className="mt-3 flex flex-wrap items-end gap-2" onSubmit={(event) => {
                        event.preventDefault();
                        ban.post(route('admin.users.ban', user.username), { preserveScroll: true, onSuccess: () => ban.reset() });
                    }}>
                        <label className="min-w-0 flex-1 text-sm">
                            Ban reason
                            <input className="field-input mt-1" value={ban.data.reason} onChange={(event) => ban.setData('reason', event.target.value)} maxLength={500} required />
                        </label>
                        <button className="btn-ghost text-ink-expl" disabled={ban.processing}>Confirm ban</button>
                        <AdminErrors errors={ban.errors} />
                    </form>
                </details>
            )}
        </li>
    );
}

export default function AdminUsers({ users, q, filter, roleOptions, canManageAdmins, previousPage, nextPage }) {
    const { auth } = usePage().props;
    const [search, setSearch] = useState(q ?? '');

    return (
        <AdminLayout>
            <Head title="People & roles" />
            <AdminTopBar title="People & roles" />
            <div className="space-y-4 p-3.5 md:p-5">
                <nav className="flex flex-wrap gap-2" aria-label="Account filters">
                    {FILTERS.map(([key, label]) => <Link key={key} href={route('admin.users', { filter: key, q })} className={filter === key ? 'chip is-active' : 'chip'}>{label}</Link>)}
                </nav>
                <form className="flex gap-2" onSubmit={(event) => {
                    event.preventDefault();
                    router.get(route('admin.users'), { q: search, filter }, { preserveState: true, replace: true });
                }}>
                    <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name, handle or email" className="field-input min-w-0" aria-label="Search accounts" />
                    <button className="btn-ghost shrink-0">Search</button>
                </form>
                {users.length ? (
                    <ul className="space-y-3">
                        {users.map((user) => <AccountRow key={JSON.stringify([user.id, user.roles, user.is_admin])} {...{ user, roleOptions, canManageAdmins }} viewer={auth.user} />)}
                    </ul>
                ) : <p className="py-10 text-center text-sm text-ink-muted">No accounts match.</p>}
                <AdminPagination {...{ previousPage, nextPage }} />
            </div>
        </AdminLayout>
    );
}
