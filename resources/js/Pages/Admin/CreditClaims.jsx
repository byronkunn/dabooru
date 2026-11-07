import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { AdminErrors, AdminPagination } from '@/Components/AdminUi';

function Claim({ claim }) {
    const { auth } = usePage().props;
    const form = useForm({ reason: '' });
    const review = (decision) => {
        form.transform((data) => ({ ...data, decision }));
        form.post(route('admin.credit-claims.review', claim.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <li className="panel space-y-2 break-words">
            <p className="font-bold">Claim #{claim.id} · {claim.status}</p>
            <p className="text-sm">{claim.artist && <Link href={route('profile.show', claim.artist)} className="link-brand">@{claim.artist}</Link>} · {claim.post
                ? <Link href={route('posts.show', claim.post.id)} className="link-brand">{claim.post.title || `Work #${claim.post.id}`}</Link>
                : `Unavailable work #${claim.post_id}`}</p>
            {claim.post?.artist_name && <p className="text-sm text-ink-muted">Original credit: {claim.post.artist_name}</p>}
            {claim.post?.source_url && <p className="break-all text-xs text-ink-muted">Source: {claim.post.source_url}</p>}
            <p className="whitespace-pre-wrap text-sm">{claim.evidence}</p>
            {claim.reason && <p className="text-sm text-ink-muted">Decision: {claim.reason}</p>}
            {auth.user?.username !== claim.artist && ['pending', 'approved'].includes(claim.status) && (
                <form className="space-y-2" onSubmit={(event) => {
                    event.preventDefault();
                    review(event.nativeEvent.submitter.value);
                }}>
                    <label className="block text-sm">Reason<input className="field-input mt-1" required maxLength={500} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></label>
                    <AdminErrors errors={form.errors} />
                    <div className="flex flex-wrap gap-2">
                        {claim.status === 'approved' ? <button className="btn-ghost" value="revoked" disabled={form.processing}>Revoke credit</button> : <>
                            {claim.approvable && <button className="btn-primary" value="approved" disabled={form.processing}>Approve credit</button>}
                            <button className="btn-ghost" value="rejected" disabled={form.processing}>Reject</button>
                        </>}
                    </div>
                </form>
            )}
        </li>
    );
}

export default function CreditClaims({ claims, status, nextPage, previousPage }) {
    return (
        <AdminLayout>
            <Head title="Artist credit claims" />
            <AdminTopBar title="Artist credit claims" />
            <div className="space-y-3 p-3.5 md:p-5">
                <nav className="flex gap-2" aria-label="Claim status">
                    {['pending', 'reviewed'].map((tab) => <Link key={tab} className={`chip capitalize ${status === tab ? 'is-active' : ''}`} href={route('admin.credit-claims', { status: tab })}>{tab}</Link>)}
                </nav>
                {claims.length ? <ul className="space-y-3">{claims.map((claim) => <Claim key={`${claim.id}-${claim.status}`} claim={claim} />)}</ul>
                    : <p className="py-10 text-center text-sm text-ink-muted">No claims in this view.</p>}
                <AdminPagination {...{ previousPage, nextPage }} />
            </div>
        </AdminLayout>
    );
}
