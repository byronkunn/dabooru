import { Head, Link } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';

const destinations = [
    ['Upload artwork', 'upload.create', 'gallery', 'Share an image, GIF, video, or album.'],
    ['Create or update a comic', 'comics.chapters.create', 'bookmark', 'Start a series or add a chapter to one you already publish.'],
    ['Open Creator Studio', 'studio.index', 'settings', 'Manage drafts, published works, and chapter uploads.'],
];

export default function CreateIndex() {
    return (
        <AppLayout rightRail={false}>
            <Head title="Create" />
            <TopBar title="Create" />
            <div className="mx-auto max-w-2xl space-y-3 p-4 md:p-5">
                {destinations.map(([label, name, icon, description]) => (
                    <Link key={name} href={route(name)} className="panel flex min-h-20 items-center gap-4 hover:border-ink-brand/50">
                        <Icon name={icon} className="h-6 w-6 shrink-0 text-ink-brand" />
                        <span className="min-w-0">
                            <span className="block font-bold">{label}</span>
                            <span className="mt-1 block text-sm text-ink-muted">{description}</span>
                        </span>
                    </Link>
                ))}
            </div>
        </AppLayout>
    );
}
