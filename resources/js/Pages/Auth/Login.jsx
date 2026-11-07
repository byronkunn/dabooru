import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword, demoAccounts = [] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', password: '', remember: false });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />
            <p className="mb-5 text-sm text-ink-muted">Welcome back.</p>

            {status && <div className="mb-4 rounded-lg border border-ink-sfw/30 bg-ink-sfw/10 px-3 py-2 text-sm text-[#7de3ad]">{status}</div>}

            {demoAccounts.length > 0 && (
                <div className="mb-5 rounded-2xl border border-ink-line bg-ink-surface p-3">
                    <p className="mb-2 px-1 text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-faint">
                        Jump in as…
                    </p>
                    <div className="space-y-1.5">
                        {demoAccounts.map((account) => (
                            <a
                                key={account.username}
                                href={account.url}
                                className="flex items-center gap-3 rounded-xl border border-transparent px-2 py-2 hover:border-ink-line hover:bg-ink-raised"
                            >
                                <img src={account.avatar} alt="" className="h-9 w-9 shrink-0 rounded-full bg-ink-raised object-cover" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-[14px] font-bold">{account.name}</span>
                                    <span className="block truncate text-[12px] text-ink-muted">{account.role}</span>
                                </span>
                                <span className="shrink-0 rounded-full bg-ink-brand px-2.5 py-1 text-[11.5px] font-extrabold text-[#1c1017]">
                                    Sign in
                                </span>
                            </a>
                        ))}
                    </div>
                    <p className="mt-2 px-1 text-[11.5px] leading-snug text-ink-faint">
                        Local demo accounts. Disabled when APP_ENV=production.
                    </p>
                </div>
            )}

            <form onSubmit={submit} className="space-y-3.5">
                <div>
                    <label htmlFor="email" className="field-label">Email</label>
                    <input id="email" type="email" value={data.email} autoComplete="username" autoFocus onChange={(e) => setData('email', e.target.value)} className="field-input" />
                    {errors.email && <p className="field-hint text-ink-expl">{errors.email}</p>}
                </div>
                <div>
                    <label htmlFor="password" className="field-label">Password</label>
                    <input id="password" type="password" value={data.password} autoComplete="current-password" onChange={(e) => setData('password', e.target.value)} className="field-input" />
                    {errors.password && <p className="field-hint text-ink-expl">{errors.password}</p>}
                </div>
                <label className="flex items-center gap-2 text-[13px] text-ink-muted">
                    <input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} className="rounded border-ink-line bg-ink-raised text-ink-brand focus:ring-ink-brand" />
                    Remember me
                </label>
                <button type="submit" disabled={processing} className="btn w-full min-h-[50px] rounded-xl bg-gradient-to-br from-ink-brand to-ink-pink text-[15px] text-[#170d13]">
                    Log in
                </button>
            </form>

            <p className="mt-4 text-center text-[13.5px] text-ink-muted">
                New here?{' '}
                <Link href={route('register')} className="font-semibold text-ink-brand hover:underline">
                    Create an account
                </Link>
            </p>
            <p className="mt-2 text-center text-[12.5px] text-ink-faint">
                All demo accounts use <span className="text-ink-muted">password</span>.
            </p>
        </GuestLayout>
    );
}
