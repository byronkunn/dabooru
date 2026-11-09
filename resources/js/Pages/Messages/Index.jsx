import { Head, usePoll } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import usePageVisibility from './usePageVisibility';
import AppLayout, { LegalFooter } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';
import Inbox from './Inbox';

export default function MessagesIndex(props) {
    const visible = usePageVisibility();
    const poll = usePoll(6000, { only: ['conversations', 'auth', 'hasMore', 'requestCount'], preserveScroll: true }, { autoStart: false });
    const { start, stop } = useRef(poll).current;
    useEffect(() => {
        if (visible) start();
        else stop();
    }, [visible, start, stop]);

    return (
        <AppLayout rightRail={false} footer={false}>
            <Head title="Messages" />
            <div className="message-window grid min-w-0 grid-cols-1 lg:grid-cols-[minmax(240px,300px)_minmax(0,1fr)]">
                <Inbox {...props} key={props.filters?.q ?? ''} />
                <div className="hidden place-items-center text-center lg:grid">
                    <div className="p-6">
                        <Icon name="chat" className="mx-auto h-10 w-10 text-ink-faint" />
                        <p className="mt-3 text-base font-semibold">Open a conversation</p>
                        <p className="mt-1 text-sm text-ink-muted">Direct messages and Saved Messages.</p>
                    </div>
                </div>
            </div>
            <LegalFooter compact className="bg-ink-bg/95 lg:pl-[min(300px,24%)]" />
        </AppLayout>
    );
}
