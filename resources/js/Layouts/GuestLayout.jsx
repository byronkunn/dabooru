import { Link } from '@inertiajs/react';

export default function GuestLayout({ children, art = null }) {
    return (
        <div className="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
            <div className="relative hidden overflow-hidden bg-gradient-to-br from-[#1b1420] via-ink-bg to-[#241a14] lg:grid lg:place-items-center lg:p-10">
                <div className="absolute inset-[-10%] grid rotate-[-6deg] grid-cols-3 gap-3 opacity-35">
                    {['#3a2740', '#241a14', '#1d1a24', '#3b2a1c', '#2a2030', '#1f1a26'].map((c, i) => (
                        <div key={i} className="aspect-square rounded-xl" style={{ background: c }} />
                    ))}
                </div>
                <div className="relative z-10 max-w-[420px]">
                    <p className="text-[34px] font-black tracking-[-0.03em]">Inkwell</p>
                    <p className="mt-3 text-base leading-relaxed text-[#d9cfe0]">
                        A gallery and social home for artists who draw what they draw. Post images, GIFs, video, albums and comics.
                        Mark your own work SFW or explicit.
                    </p>
                </div>
            </div>

            <div className="grid place-items-center px-4 py-7 sm:px-6">
                <div className="w-full max-w-[400px]">
                    <Link href={route('gallery')} className="mb-1.5 block text-[30px] font-black tracking-[-0.03em]">
                        Inkwell
                    </Link>
                    {children}
                </div>
            </div>
        </div>
    );
}
