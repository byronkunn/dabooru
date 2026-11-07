import { Head, Link, router } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';

export default function MessagesIndex({ conversations, activeId }) {
    const active = conversations.find((c) => c.id === activeId);

    return (
        <AppLayout>
            <Head title="Messages" />
            <div className="grid h-[calc(100vh-64px)] grid-cols-1 md:h-screen md:grid-cols-[320px_minmax(0,1fr)]">
                <div className="border-r border-ink-line md:overflow-y-auto">
                    <TopBar title="Messages" />
                    {conversations.length === 0 ? (
                        <p className="px-5 py-16 text-center text-sm text-ink-muted">
                            No messages yet. Visit an artist&apos;s profile and hit Message.
                        </p>
                    ) : (
                        conversations.map((c) => (
                            <Link
                                key={c.id}
                                href={route('messages.show', c.id)}
                                className={`flex min-h-[68px] items-center gap-2.5 border-b border-ink-line px-4 py-3 ${c.id === active?.id ? 'bg-ink-raised' : ''}`}
                            >
                                <img src={c.with?.avatar} alt="" className="h-11 w-11 rounded-full object-cover" />
                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-1.5 text-sm font-bold">
                                        {c.with?.name}
                                        <span className="truncate text-[12.5px] font-medium text-ink-muted">@{c.with?.username}</span>
                                    </p>
                                    <p className="truncate text-[13px] text-ink-muted">{c.preview}</p>
                                </div>
                                <span className="shrink-0 text-[11.5px] text-ink-faint">{c.last_message_human}</span>
                            </Link>
                        ))
                    )}
                </div>

                <div className="hidden md:grid md:place-items-center md:text-center">
                    <div className="max-w-xs">
                        <Icon name="chat" className="mx-auto h-10 w-10 text-ink-faint" />
                        <p className="mt-3 text-[15px] font-semibold">Select a conversation</p>
                        <p className="mt-1 text-[13px] text-ink-muted">Messages between you and other artists live here.</p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
