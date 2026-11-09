import { Head, Link, router } from '@inertiajs/react';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export default function Gate() {
    return (
        <div className="grid min-h-screen place-items-center bg-[radial-gradient(900px_500px_at_50%_-10%,rgba(201,82,122,0.18),transparent_70%)] px-4 py-6">
            <Head title="Age confirmation" />
            <div className="w-full max-w-[520px] rounded-[22px] border border-ink-line bg-ink-surface p-6 text-center md:p-8">
                <p className="text-[26px] font-black tracking-[-0.03em]">Dabooru</p>
                <p className="mt-1 text-[13px] font-bold uppercase tracking-[0.14em] text-ink-muted">Adult content warning</p>
                <p className="mx-auto mt-4 max-w-[420px] text-sm leading-relaxed text-ink-muted">
                    This site hosts artwork for adults, including explicit illustrations, comics and GIFs. You must be{' '}
                    <b className="text-ink-text">18 or older</b> to enter. SFW work is welcome here too — NSFW work is marked per post.
                </p>
                <div className="mt-5 space-y-2.5">
                    <button type="button" onClick={() => router.post(route('age-gate.confirm'))} className="btn w-full min-h-[52px] rounded-[14px] bg-gradient-to-br from-ink-brand to-ink-pink text-[15px] text-[#170d13]">
                        I am 18 or older — Enter
                    </button>
                    {/* Leave must be a real top-level navigation: posting it through
                        Inertia makes it an XHR, and the browser blocks a cross-origin
                        redirect with CORS, leaving the visitor stuck on the gate. */}
                    <form method="POST" action={route('age-gate.leave')} className="contents">
                        <input type="hidden" name="_token" value={csrfToken()} />
                        <button type="submit" className="btn w-full min-h-[52px] rounded-[14px] border border-[#4a3c58] text-[15px]">
                            Leave this site
                        </button>
                    </form>
                </div>
                <p className="mt-4 text-[11.5px] leading-relaxed text-ink-faint">
                    By entering you confirm you are of legal age to view adult content in your jurisdiction. We use a cookie to remember this choice.
                </p>

                {/* the gate is the first screen anyone sees, so the legal pages must be reachable from it */}
                <nav aria-label="Legal" className="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-1 border-t border-ink-line pt-4 text-[12px] text-ink-muted">
                    <Link href={route('legal.terms')} className="hover:text-ink-text">Terms</Link>
                    <Link href={route('legal.privacy')} className="hover:text-ink-text">Privacy</Link>
                    <Link href={route('legal.policy')} className="hover:text-ink-text">Content policy</Link>
                    <Link href={route('dmca')} className="hover:text-ink-text">Copyright / DMCA</Link>
                </nav>
            </div>
        </div>
    );
}
