import { useForm } from '@inertiajs/react';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { useState } from 'react';
import { Icon } from '@/Components/Icons';
import { AdminErrors } from '@/Components/AdminUi';

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
    const form = useForm({});

    const submit = (e) => {
        e.preventDefault();
        if (!reason) return;
        form.transform(() => ({ reportable_type: type, reportable_id: id, reason, details }));
        form.post(route('reports.store'), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onClose={onClose} className="fixed inset-0 z-[80]">
            <div className="fixed inset-0 bg-black/70" aria-hidden="true" />
            <div className="fixed inset-0 grid place-items-center overflow-y-auto p-4">
            <DialogPanel as="form" onSubmit={submit} onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    onClose();
                }
            }} className="w-full max-w-[420px] rounded-2xl border border-ink-line bg-ink-surface p-5">
                <div className="mb-3 flex items-center gap-2">
                    <Icon name="flag" className="h-5 w-5 text-[#ff98a9]" />
                    <DialogTitle className="text-[16px] font-bold">Report to moderators</DialogTitle>
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
                    <button type="submit" disabled={!reason || form.processing} className="btn-primary">
                        {form.processing ? 'Sending…' : 'Send report'}
                    </button>
                </div>
                <AdminErrors errors={form.errors} />
                <p className="mt-2 text-[11.5px] leading-snug text-ink-faint">
                    Reports go to a moderator queue. False reports for copyright are a legal matter for you.
                </p>
            </DialogPanel>
            </div>
        </Dialog>
    );
}
