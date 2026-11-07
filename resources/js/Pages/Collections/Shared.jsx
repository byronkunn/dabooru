import { Head, Link } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Masonry } from '@/Components/Work';
import { AdminPagination } from '@/Components/AdminUi';

export default function Shared({ name, owner, posts, nextPage, previousPage }) {
    return (
        <AppLayout>
            <Head title={name} />
            <TopBar title={name} />
            <div className="space-y-4 p-3.5 md:p-5">
                <p className="text-sm text-ink-muted">A collection shared by <Link href={route('profile.show', owner)} className="link-brand">@{owner}</Link></p>
                {posts.length > 0 ? <Masonry posts={posts} /> : <p className="py-12 text-center text-sm text-ink-muted">No visible works in this collection.</p>}
                <AdminPagination {...{ previousPage, nextPage }} />
            </div>
        </AppLayout>
    );
}
