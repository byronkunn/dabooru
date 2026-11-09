import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';

function CopyButton({ value, label }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1600);
        } catch {
            setCopied(false);
        }
    };

    return (
        <button type="button" onClick={copy} className="btn-ghost h-9 shrink-0 px-2.5 text-[12px]" aria-label={`Copy ${label} address`}>
            {copied ? <Icon name="check" className="h-4 w-4 text-ink-sfw" /> : <Icon name="save" className="h-4 w-4" />}
        </button>
    );
}

export default function TipIndex({ networks = [], ownerName, isOwner = false }) {
    const form = useForm({ addresses: Object.fromEntries(networks.map((n) => [n.network, n.address ?? ''])) });
    const configured = networks.filter((n) => n.configured);

    return (
        <AppLayout rightRail={false}>
            <Head title="Tip" />
            <TopBar title="Tip" back={route('gallery')} />

            <div className="mx-auto max-w-[640px] px-4 pb-24 pt-5 md:px-5">
                <p className="text-[13.5px] leading-relaxed text-ink-muted">
                    Tips go straight to {ownerName} to keep the site and its hosting running. Send only the listed
                    coin on its own network — anything else sent to these addresses is lost.
                </p>

                {isOwner && (
                    <p className="mt-3 rounded-xl border border-ink-brand/40 bg-ink-brand/10 px-3 py-2 text-[12.5px] text-ink-brand">
                        You are the owner. Only you can see and edit the full addresses below.
                    </p>
                )}

                <form
                    className="mt-5 space-y-2.5"
                    onSubmit={(e) => { e.preventDefault(); form.patch(route('tip.update'), { preserveScroll: true }); }}
                >
                    {networks.map((network) => (
                        <div key={network.network} className="rounded-xl border border-ink-line bg-ink-surface p-3">
                            <div className="flex items-center gap-2">
                                <span className="text-[14px] font-bold">{network.label}</span>
                                <span className="rounded bg-ink-raised px-1.5 py-0.5 text-[11px] font-bold text-ink-muted">{network.symbol}</span>
                                {network.hint && <span className="text-[11.5px] text-ink-faint">{network.hint}</span>}
                                {!network.configured && <span className="ml-auto text-[11.5px] text-ink-faint">Not set up yet</span>}
                            </div>

                            {isOwner ? (
                                <input
                                    value={form.data.addresses[network.network] ?? ''}
                                    onChange={(e) => form.setData('addresses', { ...form.data.addresses, [network.network]: e.target.value })}
                                    placeholder={`Your ${network.symbol} address`}
                                    aria-label={`${network.label} address`}
                                    className="field-input mt-2 min-h-[40px] w-full font-mono text-[12.5px]"
                                />
                            ) : network.configured ? (
                                <div className="mt-2 flex items-center gap-2">
                                    <code className="min-w-0 flex-1 truncate rounded-lg bg-ink-raised px-2.5 py-2 font-mono text-[12px] text-ink-text">
                                        {network.address ?? network.symbol.toLowerCase()}
                                    </code>
                                    {network.address && <CopyButton value={network.address} label={network.label} />}
                                </div>
                            ) : null}
                        </div>
                    ))}

                    {isOwner && (
                        <button type="submit" disabled={form.processing} className="btn-primary min-h-11 w-full">
                            {form.processing ? 'Saving…' : 'Save tip addresses'}
                        </button>
                    )}
                </form>

                {!isOwner && configured.length === 0 && (
                    <p className="mt-6 rounded-xl border border-dashed border-ink-line px-3 py-8 text-center text-[13px] text-ink-muted">
                        No tip addresses have been set up yet.
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
