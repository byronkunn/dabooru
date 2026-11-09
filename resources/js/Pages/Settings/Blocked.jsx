import { Head, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { Icon } from '@/Components/Icons';
import { Errors } from '@/Components/KnowledgeUi';

export default function Blocked({ blocks }) {
    const form = useForm({});
    const unblock = (block) => form.delete(route('users.unblock', block.user.username), { preserveScroll: true });

    return (
        <AppLayout rightRail={false}>
            <Head title="Blocked accounts" />
            <TopBar title="Blocked accounts" back={`${route('settings.edit')}#blocked`} />

            <div className="mx-auto max-w-[720px]">
                <p className="px-4 pt-4 text-[13px] leading-relaxed text-ink-muted md:px-5">
                    Blocking someone hides their work from your gallery and feed, ends any following
                    between you, and stops them messaging you. They are not told.
                </p>
                <Errors errors={form.errors} />

                {blocks.length === 0 ? (
                    <p className="px-5 py-16 text-center text-sm text-ink-muted">You haven&apos;t blocked anyone.</p>
                ) : (
                    <ul className="mt-3">
                        {blocks.map((block) => (
                            <li key={block.id} className="flex items-center gap-3 border-b border-ink-line px-4 py-3 md:px-5">
                                <img src={block.user.avatar} alt="" className="h-10 w-10 rounded-full bg-ink-raised object-cover" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-[14px] font-bold">
                                        {block.user.name} <span className="font-medium text-ink-muted">@{block.user.username}</span>
                                    </p>
                                    <p className="text-[12px] text-ink-faint">Blocked {block.when}</p>
                                </div>
                                <button type="button" disabled={form.processing} onClick={() => unblock(block)} className="btn-ghost min-h-[36px] text-[12.5px]">
                                    Unblock
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                <Link href={`${route('settings.edit')}#blocked`} className="mx-4 mt-6 inline-flex items-center gap-1.5 text-[13px] text-ink-muted hover:text-ink-text md:mx-5">
                    <Icon name="back" className="h-4 w-4" /> Back to settings
                </Link>
            </div>
        </AppLayout>
    );
}
