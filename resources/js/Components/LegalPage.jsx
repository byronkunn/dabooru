import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export function LegalPage({ title, updated, children, intro }) {
    return (
        <AppLayout rightRail={false}>
            <Head title={title} />
            <article className="mx-auto max-w-[720px] px-4 pb-24 pt-8 md:px-6">
                <p className="text-[11.5px] font-extrabold uppercase tracking-[0.14em] text-ink-faint">BooruHub</p>
                <h1 className="mt-1 text-[28px] font-extrabold tracking-[-0.03em] md:text-[34px]">{title}</h1>
                <p className="mt-1 text-[13px] text-ink-faint">Last updated {updated}</p>
                {intro && <p className="mt-5 rounded-xl border border-ink-line bg-ink-surface px-4 py-3 text-[13.5px] leading-relaxed text-ink-muted">{intro}</p>}
                <div className="legal mt-7 space-y-6 text-[15px] leading-relaxed text-[#dfd7e5]">{children}</div>
                <nav className="mt-12 flex flex-wrap gap-2 border-t border-ink-line pt-6 text-[13px]">
                    <Link href={route('legal.terms')} className="chip-plain">Terms of service</Link>
                    <Link href={route('legal.privacy')} className="chip-plain">Privacy policy</Link>
                    <Link href={route('legal.policy')} className="chip-plain">Content policy</Link>
                    <Link href={route('dmca')} className="chip-plain">Copyright / DMCA</Link>
                </nav>
            </article>
        </AppLayout>
    );
}

export function H2({ children }) {
    return <h2 className="text-[17px] font-bold tracking-[-0.01em] text-ink-text">{children}</h2>;
}

export function P({ children }) {
    return <p className="text-[15px] leading-relaxed text-[#d8d0dd]">{children}</p>;
}

export function UL({ children }) {
    return <ul className="list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-[#d8d0dd]">{children}</ul>;
}
