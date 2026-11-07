import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';
import { useEffect, useRef } from 'react';

export default function MessagesShow({ conversation }) {
    const { auth } = usePage().props;
    const form = useForm({ conversation_id: conversation.id, body: '' });
    const endRef = useRef(null);

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [conversation.messages.length]);

    const send = (e) => {
        e.preventDefault();
        if (!form.data.body.trim()) return;
        form.post(route('messages.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
        });
    };

    return (
        <AppLayout rightRail={false}>
            <Head title={`Chat with ${conversation.with?.name}`} />
            <div className="flex h-[calc(100vh-64px)] flex-col md:h-screen">
                <header className="flex min-h-[58px] shrink-0 items-center gap-2.5 border-b border-ink-line px-3 py-2.5">
                    <Link href={route('messages.index')} aria-label="Back" className="btn-icon-flat -ml-1 md:hidden">
                        <Icon name="back" />
                    </Link>
                    <img src={conversation.with?.avatar} alt="" className="h-9 w-9 rounded-full object-cover" />
                    <div className="min-w-0">
                        <Link href={route('profile.show', conversation.with?.username)} className="block truncate text-[14.5px] font-bold hover:underline">
                            {conversation.with?.name}
                        </Link>
                        <p className="truncate text-[12.5px] text-ink-muted">@{conversation.with?.username}</p>
                    </div>
                </header>

                <div className="flex-1 space-y-2.5 overflow-y-auto px-4 py-4">
                    {conversation.messages.map((m) => (
                        <div key={m.id} className={`max-w-[78%] rounded-2xl px-3.5 py-2.5 text-[14.5px] leading-snug ${m.mine ? 'ml-auto rounded-br-[5px] bg-gradient-to-br from-[#5a3b1f] to-[#4a2436]' : 'rounded-bl-[5px] bg-ink-raised'}`}>
                            <p className="whitespace-pre-wrap">{m.body}</p>
                            <span className="mt-1 block text-[10.5px] text-white/45">{m.created_at_human}</span>
                        </div>
                    ))}
                    <div ref={endRef} />
                </div>

                <form onSubmit={send} className="flex shrink-0 items-center gap-2.5 border-t border-ink-line px-3.5 py-3">
                    <input
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        placeholder="Start a message"
                        aria-label="Message"
                        className="field-input rounded-full bg-ink-raised"
                    />
                    <button type="submit" disabled={!form.data.body.trim() || form.processing} className="btn-primary h-11 w-11 shrink-0 p-0" aria-label="Send">
                        <Icon name="send" className="h-5 w-5" />
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}
