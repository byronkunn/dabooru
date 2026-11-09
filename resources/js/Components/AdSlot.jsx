import { usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AdSlot({ placement, compact = false, afterWork, className = '', fallback }) {
    const { sponsorAd } = usePage().props;
    const [failedImage, setFailedImage] = useState(null);
    const hasAd = sponsorAd?.image && sponsorAd?.url;

    if (!hasAd) return fallback?.('unconfigured') ?? null;
    if (failedImage === sponsorAd.image) return fallback?.('image-error') ?? null;

    return (
        <aside aria-label="Advertisement" data-ad-slot={placement} data-after-work={afterWork} className={`overflow-hidden rounded-card border border-ink-line bg-ink-surface ${className}`}>
            <p className="border-b border-ink-line px-3 py-1 text-[9px] font-bold uppercase tracking-widest text-ink-faint">Advertisement</p>
            <div className={compact ? 'h-[70px]' : 'h-[90px] lg:h-[190px]'}>
                <a href={sponsorAd.url} target="_blank" rel="sponsored noopener noreferrer" className="flex h-full w-full items-center justify-center">
                    <img src={sponsorAd.image} alt={sponsorAd.label} loading="lazy" className="h-full w-full object-contain" onError={() => setFailedImage(sponsorAd.image)} />
                </a>
            </div>
        </aside>
    );
}
