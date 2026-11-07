import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Icon } from '@/Components/Icons';

const REASONS = [
    ['minor', 'Minor / underage'],
    ['non_consensual', 'Non-consensual or stolen'],
    ['spam', 'Spam or scam'],
    ['copyright', 'Copyright'],
    ['other', 'Something else'],
];

export default function ReportDialog({ type, id, onClose }) {
    const [reason, setReason] = useState('');
    const [details, setDetails] = useState('');
    const [sending, setSending] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        if (!reason) return;
        setSending(true);
        router.post(
            route('reports.store'),
            { reportable_type: type, reportable_id: id, reason, details },
            { preserveScroll: true, onSuccess: onClose, onFinish: () => setSending(false) },
        );
    };

    return (
        <div className="fixed inset-0 z-[80] grid place-items-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="report-title">
            <form onSubmit={submit} className="w-full max-w-[420px] rounded-2xl border border-ink-line bg-ink-surface p-5">
                <div className="mb-3 flex items-center gap-2">
                    <Icon name="flag" className="h-5 w-5 text-[#ff98a9]" />
                    <h2 id="report-title" className="text-[16px] font-bold">Report to moderators</h2>
                    <button type="button" onClick={onClose} aria-label="Close" className="btn-icon-flat ml-auto h-8 w-8">
                        <Icon name="close" className="h-4 w-4" />
                    </button>
                </div>

                <fieldset className="space-y-1.5">
                    <legend className="field-label">Reason</legend>
                    {REASONS.map(([key, label]) => (
                        <label key={key} className={`flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-[13.5px] ${reason === key ? 'border-ink-brand bg-ink-brand/10 text-ink-brand' : 'border-ink-line text-ink-muted hover:text-ink-text'}`}>
                            <input type="radio" name="reason" value={key} checked={reason === key} onChange={() => setReason(key)} className="h-4 w-4 border-ink-line bg-ink-raised text-ink-brand focus:ring-ink-brand" />
                            {label}
                        </label>
                    ))}
                </fieldset>

                <label htmlFor="report-details" className="field-label mt-3 block">Anything else (optional)</label>
                <textarea id="report-details" rows={3} value={details} onChange={(e) => setDetails(e.target.value)} maxLength={1000} className="field-input resize-none" />

                <div className="mt-4 flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="btn-ghost">Cancel</button>
                    <button type="submit" disabled={!reason || sending} className="btn-primary">
                        {sending ? 'Sending…' : 'Send report'}
                    </button>
                </div>
                <p className="mt-2 text-[11.5px] leading-snug text-ink-faint">
                    Reports go to a moderator queue. False reports for copyright are a legal matter for you.
                </p>
            </form>
        </div>
    );
}
