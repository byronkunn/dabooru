import { Head, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import QRCode from 'qrcode';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';
import { CryptoMark } from '@/Components/CryptoMark';
import { copyText } from '@/Lib/clipboard';

const NETWORK_TINT = {
    monero: 'from-[#ff6600] to-[#f26822]', bitcoin: 'from-[#f7931a] to-[#c76e00]',
    ethereum: 'from-[#8a9cf0] to-[#627eea]', solana: 'from-[#9945ff] to-[#14f195]',
};
const DECIMALS = { monero: 12, bitcoin: 8, ethereum: 18, solana: 9 };

function paymentAmount(usd, price, decimals) {
    if (!price || !usd) return '';
    const value = Number(usd) / Number(price);
    if (!Number.isFinite(value) || value <= 0) return '';
    return value.toFixed(decimals).replace(/\.?0+$/, '');
}

function paymentUri(network, address, coinAmount) {
    if (!coinAmount) return `${network === 'ethereum' ? 'ethereum' : network === 'bitcoin' ? 'bitcoin' : network === 'solana' ? 'solana' : 'monero'}:${address}`;
    if (network === 'bitcoin') return `bitcoin:${address}?amount=${coinAmount}`;
    if (network === 'solana') return `solana:${address}?amount=${coinAmount}`;
    if (network === 'monero') return `monero:${address}?tx_amount=${coinAmount}`;
    const [whole, fraction = ''] = coinAmount.split('.');
    const wei = BigInt(whole) * (10n ** 18n) + BigInt(fraction.padEnd(18, '0'));
    return `ethereum:${address}?value=${wei}`;
}

function AddressRow({ network, usd, prices }) {
    const [copied, setCopied] = useState('');
    const [showQr, setShowQr] = useState(false);
    const [qr, setQr] = useState('');
    const tint = NETWORK_TINT[network.network] ?? 'from-ink-brand to-ink-pink';
    const address = network.address ?? '';
    const short = address ? `${address.slice(0, 8)}…${address.slice(-6)}` : '';
    const amount = paymentAmount(usd, prices?.[network.network], DECIMALS[network.network] ?? 8);
    const uri = address ? paymentUri(network.network, address, amount) : '';

    useEffect(() => {
        let active = true;
        if (showQr && uri) QRCode.toDataURL(uri, { width: 240, margin: 2, errorCorrectionLevel: 'M' })
            .then((data) => { if (active) setQr(data); })
            .catch(() => { if (active) setQr(''); });
        return () => { active = false; };
    }, [showQr, uri]);

    const copy = async (text, what) => {
        try {
            if (!await copyText(text)) throw new Error('Copy failed');
            setCopied(what);
            setTimeout(() => setCopied(''), 1800);
        } catch { setCopied(''); }
    };

    return (
        <li className="overflow-hidden rounded-2xl border border-ink-line bg-ink-surface transition-colors hover:border-[#4a3c58]">
            <div className="flex items-center gap-3 p-3">
                <span aria-hidden="true" className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br ${tint} text-white shadow-inner`}>
                    <CryptoMark network={network.network} className="h-6 w-6 drop-shadow-sm" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-x-1.5 text-[14px] font-bold">
                        {network.label}
                        <span className="rounded bg-ink-raised px-1.5 py-0.5 text-[10.5px] font-bold text-ink-muted">{network.symbol}</span>
                        {network.hint && <span className="hidden text-[11px] font-normal text-ink-faint sm:inline">{network.hint}</span>}
                    </p>
                    {address ? <code className="mt-0.5 block truncate font-mono text-[12px] text-ink-muted" title={address}>{short}</code> : <p className="mt-0.5 text-[12px] text-ink-faint">Not set up yet</p>}
                    {amount && <p className="mt-1 text-[12px] font-semibold text-ink-brand">≈ {amount} {network.symbol}</p>}
                </div>
                {address && <div className="flex shrink-0 gap-1.5">
                    <button type="button" onClick={() => setShowQr((value) => !value)} aria-expanded={showQr} aria-label={`${showQr ? 'Hide' : 'Show'} ${network.label} QR code`} className="grid h-9 w-9 place-items-center rounded-lg bg-ink-raised text-ink-text hover:bg-[#332b3c]"><Icon name="qr" className="h-4 w-4" /></button>
                    <button type="button" onClick={() => copy(address, 'address')} aria-label={`Copy ${network.label} address`} className={`flex h-9 items-center justify-center rounded-lg px-3 text-[12px] font-semibold transition-colors ${copied === 'address' ? 'bg-ink-sfw/15 text-ink-sfw' : 'bg-ink-raised text-ink-text hover:bg-[#332b3c]'}`}>
                        <span className="flex items-center gap-1.5"><Icon name={copied === 'address' ? 'check' : 'save'} className="h-4 w-4" />{copied === 'address' ? 'Copied' : 'Copy'}</span>
                    </button>
                </div>}
            </div>
            {showQr && address && <div className="border-t border-ink-line px-4 py-4 text-center">
                {qr ? <img src={qr} alt={`${network.label} tip QR code${amount ? ` for about ${amount} ${network.symbol}` : ''}`} className="mx-auto h-48 w-48 rounded-xl bg-white p-2" /> : <p className="py-16 text-sm text-ink-muted">Preparing QR code…</p>}
                {amount && <p className="mt-2 text-[13px] font-semibold">Scan to send about {amount} {network.symbol}</p>}
                <button type="button" onClick={() => copy(uri, 'request')} className="mt-2 rounded-lg bg-ink-raised px-3 py-2 text-[12px] font-semibold text-ink-text hover:bg-[#332b3c]">
                    {copied === 'request' ? 'Payment request copied' : 'Copy payment request'}
                </button>
                <p className="mt-2 text-[11px] text-ink-faint">Open your wallet and scan, or paste the copied request. Verify the coin and amount before sending.</p>
            </div>}
        </li>
    );
}

export default function TipIndex({ networks = [], ownerName, isOwner = false, prices = {}, priceUpdatedAt }) {
    const { auth } = usePage().props;
    const [usd, setUsd] = useState('5.00');
    const configured = networks.filter((n) => n.configured);
    const anySet = configured.length > 0;
    const validAmount = useMemo(() => /^\d{1,5}(\.\d{0,2})?$/.test(usd) && Number(usd) >= 1 && Number(usd) <= 5000, [usd]);

    return (
        <AppLayout rightRail={false}>
            <Head title="Tip" />
            <TopBar title="Tip" back={route('gallery')} />
            <div className="mx-auto max-w-[600px] px-4 pb-24 pt-6 md:px-5">
                <div className="text-center">
                    <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-ink-brand to-ink-pink text-[26px]"><Icon name="heart" className="h-7 w-7 text-[#1a1017]" /></span>
                    <p className="mx-auto mt-3 max-w-[380px] text-[13.5px] leading-relaxed text-ink-muted">Tips go straight to {ownerName} and keep the hosting, storage and moderation running. No account needed, nothing is stored about you.</p>
                </div>
                {isOwner && <div className="mt-5 flex flex-wrap items-center gap-2 rounded-xl border border-ink-brand/40 bg-ink-brand/10 px-3 py-2.5 text-[12.5px] text-ink-brand"><Icon name="flag" className="h-4 w-4 shrink-0" /><span className="min-w-0 flex-1">You are the owner — these are your own wallets.</span><a href={route('admin.tips')} className="shrink-0 font-bold underline">Edit</a></div>}
                {anySet ? <>
                    <section className="mt-6 rounded-2xl border border-ink-line bg-ink-surface p-4">
                        <label htmlFor="tip-usd" className="block text-[13px] font-bold">Choose an exact tip amount in US dollars</label>
                        <div className="mt-2 flex items-center gap-2 rounded-xl border border-ink-line bg-ink-raised px-3 focus-within:border-ink-brand">
                            <span className="text-lg font-bold text-ink-muted">$</span>
                            <input id="tip-usd" type="number" inputMode="decimal" min="1" max="5000" step="0.01" value={usd} onChange={(event) => setUsd(event.target.value)} className="w-full border-0 bg-transparent py-3 text-lg font-bold text-ink-text outline-none ring-0 focus:ring-0" aria-describedby="tip-usd-help" />
                            <span className="text-xs font-semibold text-ink-faint">USD</span>
                        </div>
                        <p id="tip-usd-help" className="mt-2 text-[11.5px] leading-relaxed text-ink-faint">Enter $1–$5,000, up to two cents decimals. Coin amounts use cached market rates and can change before you send.</p>
                        {priceUpdatedAt && <p className="mt-1 text-[10.5px] text-ink-faint">Rates are refreshed about every 5 minutes. Network fees are separate.</p>}
                    </section>
                    <ul className="mt-3 space-y-2" aria-label="Tip addresses">
                        {configured.map((network) => <AddressRow key={network.network} network={network} usd={validAmount ? usd : ''} prices={prices} />)}
                    </ul>
                    <div className="mt-5 space-y-2 rounded-xl border border-ink-line bg-ink-surface/60 px-3.5 py-3 text-[12px] leading-relaxed text-ink-muted">
                        <p className="flex items-start gap-2"><Icon name="flag" className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-expl" /><span><b className="text-ink-text">Send only the matching coin on its own network.</b> Anything else sent to these addresses is lost, and no one can recover it.</span></p>
                        <p className="flex items-start gap-2"><Icon name="check" className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-faint" /><span>The USD value is an estimate at the displayed market rate. Your wallet will ask you to confirm the final transaction.</span></p>
                    </div>
                </> : <div className="mt-6 rounded-2xl border border-dashed border-ink-line px-4 py-12 text-center"><Icon name="heart" className="mx-auto h-8 w-8 text-ink-faint" /><p className="mt-3 text-[14px] font-semibold">No wallets set up yet</p><p className="mt-1 text-[12.5px] text-ink-muted">Check back soon.</p></div>}
                <p className="mt-6 text-center text-[12px] text-ink-faint">Prefer to support artists directly? Follow them and share their work.{!auth?.user && <> <a href={route('register')} className="font-semibold text-ink-brand hover:underline">Make an account</a>.</>}</p>
            </div>
        </AppLayout>
    );
}
