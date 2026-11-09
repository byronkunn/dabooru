import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';

const NETWORK_MARK = {
    monero: { symbol: 'ɱ', tint: 'from-[#f26822] to-[#ff9d4d]' },
    bitcoin: { symbol: '₿', tint: 'from-[#f7931a] to-[#ffb84d]' },
    ethereum: { symbol: 'ETH', tint: 'from-[#627eea] to-[#8ea1f5]' },
    solana: { symbol: '◎', tint: 'from-[#9945ff] to-[#14f195]' },
};

function AddressRow({ network }) {
    const [copied, setCopied] = useState(false);
    const mark = NETWORK_MARK[network.network] ?? { symbol: '?', tint: 'from-ink-brand to-ink-pink' };
    const address = network.address ?? '';
    const short = address ? `${address.slice(0, 8)}…${address.slice(-6)}` : '';

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(address);
            setCopied(true);
            setTimeout(() => setCopied(false), 1800);
        } catch {
            setCopied(false);
        }
    };

    return (
        <li className="group flex items-center gap-3 rounded-2xl border border-ink-line bg-ink-surface p-3 transition-colors hover:border-[#4a3c58]">
            <span
                aria-hidden="true"
                className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br ${mark.tint} text-[17px] font-black tracking-[-0.02em] text-black/80`}
            >
                {mark.symbol}
            </span>

            <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-x-1.5 text-[14px] font-bold">
                    {network.label}
                    <span className="rounded bg-ink-raised px-1.5 py-0.5 text-[10.5px] font-bold text-ink-muted">{network.symbol}</span>
                    {network.hint && <span className="hidden text-[11px] font-normal text-ink-faint sm:inline">{network.hint}</span>}
                </p>
                {address ? (
                    <code className="mt-0.5 block truncate font-mono text-[12px] text-ink-muted" title={address}>{short}</code>
                ) : (
                    <p className="mt-0.5 text-[12px] text-ink-faint">Not set up yet</p>
                )}
            </div>

            {address ? (
                <button
                    type="button"
                    onClick={copy}
                    aria-label={`Copy ${network.label} address`}
                    className={`flex h-9 shrink-0 items-center justify-center rounded-lg px-3 text-[12px] font-semibold transition-colors ${
                        copied ? 'bg-ink-sfw/15 text-ink-sfw' : 'bg-ink-raised text-ink-text hover:bg-[#332b3c]'
                    }`}
                >
                    <span className="flex items-center gap-1.5">
                        <Icon name={copied ? 'check' : 'save'} className="h-4 w-4" />
                        {copied ? 'Copied' : 'Copy'}
                    </span>
                </button>
            ) : (
                <span className="h-9 shrink-0 px-3 text-[12px] leading-9 text-ink-faint">—</span>
            )}
        </li>
    );
}

export default function TipIndex({ networks = [], ownerName, isOwner = false }) {
    const { auth } = usePage().props;
    const configured = networks.filter((n) => n.configured);
    const anySet = configured.length > 0;

    return (
        <AppLayout rightRail={false}>
            <Head title="Tip" />

            <div className="mx-auto max-w-[600px] px-4 pb-24 pt-8 md:px-5">
                <div className="text-center">
                    <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-ink-brand to-ink-pink text-[26px]">
                        <Icon name="heart" className="h-7 w-7 text-[#1a1017]" />
                    </span>
                    <h1 className="mt-3 text-[26px] font-extrabold tracking-[-0.03em]">Tip the site</h1>
                    <p className="mx-auto mt-2 max-w-[380px] text-[13.5px] leading-relaxed text-ink-muted">
                        Tips go straight to {ownerName} and keep the hosting, storage and moderation running.
                        No account needed, nothing is stored about you.
                    </p>
                </div>

                {isOwner && (
                    <div className="mt-5 flex flex-wrap items-center gap-2 rounded-xl border border-ink-brand/40 bg-ink-brand/10 px-3 py-2.5 text-[12.5px] text-ink-brand">
                        <Icon name="flag" className="h-4 w-4 shrink-0" />
                        <span className="min-w-0 flex-1">You are the owner — these are your own wallets.</span>
                        <a href={route('admin.tips')} className="shrink-0 font-bold underline">Edit</a>
                    </div>
                )}

                {anySet ? (
                    <ul className="mt-6 space-y-2" aria-label="Tip addresses">
                        {networks.filter((n) => n.configured).map((network) => (
                            <AddressRow key={network.network} network={network} />
                        ))}
                    </ul>
                ) : (
                    <div className="mt-6 rounded-2xl border border-dashed border-ink-line px-4 py-12 text-center">
                        <Icon name="heart" className="mx-auto h-8 w-8 text-ink-faint" />
                        <p className="mt-3 text-[14px] font-semibold">No wallets set up yet</p>
                        <p className="mt-1 text-[12.5px] text-ink-muted">Check back soon.</p>
                    </div>
                )}

                {anySet && (
                    <div className="mt-5 space-y-2 rounded-xl border border-ink-line bg-ink-surface/60 px-3.5 py-3 text-[12px] leading-relaxed text-ink-muted">
                        <p className="flex items-start gap-2">
                            <Icon name="flag" className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-expl" />
                            <span><b className="text-ink-text">Send only the matching coin on its own network.</b> Anything else sent to these addresses is lost, and no one can recover it.</span>
                        </p>
                        <p className="flex items-start gap-2">
                            <Icon name="check" className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-faint" />
                            <span>Monero is private by default — the sender's address is not public.</span>
                        </p>
                    </div>
                )}

                <p className="mt-6 text-center text-[12px] text-ink-faint">
                    Prefer to support artists directly? Follow them and share their work.
                    {!auth?.user && <> <a href={route('register')} className="font-semibold text-ink-brand hover:underline">Make an account</a>.</>}
                </p>
            </div>
        </AppLayout>
    );
}
