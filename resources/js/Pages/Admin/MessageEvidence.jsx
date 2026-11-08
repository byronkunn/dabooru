import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function MessageEvidence({ reportId, body, attachment }) {
    const [show, setShow] = useState(false);
    return (
        <AdminLayout>
            <Head title={`Message evidence · Report #${reportId}`} />
            <section className="space-y-4 p-5">
                <Link className="link-brand text-sm" href={route('admin.reports', { report: reportId })}>Back to report #{reportId}</Link>
                <h1 className="text-xl font-bold">Reported message</h1>
                <p className="text-sm text-ink-muted">Access to this evidence is logged. Only the reported message is available here.</p>
                {body && <p className="whitespace-pre-wrap break-words rounded-xl bg-ink-raised p-4">{body}</p>}
                {attachment && (show ? (
                    attachment.kind === 'video' ? <video controls playsInline src={attachment.url} className="max-h-96 max-w-full" /> :
                    attachment.kind === 'audio' ? <audio controls src={attachment.url} className="max-w-full" /> :
                        <img src={attachment.url} alt="Reported attachment" className="max-h-96 max-w-full object-contain" />
                ) : <button type="button" className="btn-ghost" onClick={() => setShow(true)}>Reveal reported attachment</button>)}
            </section>
        </AdminLayout>
    );
}
