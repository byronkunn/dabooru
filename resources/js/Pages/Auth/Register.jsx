import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
        date_of_birth: '',
        age_confirmed: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <GuestLayout>
            <Head title="Create account" />
            <p className="mb-5 text-sm text-ink-muted">Create an account.</p>

            <form onSubmit={submit} className="space-y-3.5">
                <div>
                    <label htmlFor="email" className="field-label">Email</label>
                    <input id="email" type="email" value={data.email} autoComplete="username" onChange={(e) => setData('email', e.target.value)} className="field-input" placeholder="you@example.com" />
                    {errors.email && <p className="field-hint text-ink-expl">{errors.email}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label htmlFor="name" className="field-label">Display name</label>
                        <input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} className="field-input" />
                        {errors.name && <p className="field-hint text-ink-expl">{errors.name}</p>}
                    </div>
                    <div>
                        <label htmlFor="username" className="field-label">Handle</label>
                        <input id="username" value={data.username} onChange={(e) => setData('username', e.target.value)} className="field-input" placeholder="mira_ink" maxLength={30} />
                        {errors.username && <p className="field-hint text-ink-expl">{errors.username}</p>}
                    </div>
                </div>
                <div>
                    <label htmlFor="password" className="field-label">Password</label>
                    <input id="password" type="password" value={data.password} autoComplete="new-password" onChange={(e) => setData('password', e.target.value)} className="field-input" />
                    {errors.password && <p className="field-hint text-ink-expl">{errors.password}</p>}
                </div>
                <div>
                    <label htmlFor="password_confirmation" className="field-label">Confirm password</label>
                    <input id="password_confirmation" type="password" value={data.password_confirmation} autoComplete="new-password" onChange={(e) => setData('password_confirmation', e.target.value)} className="field-input" />
                </div>
                <div>
                    <label htmlFor="date_of_birth" className="field-label">Date of birth</label>
                    <input id="date_of_birth" type="date" value={data.date_of_birth} onChange={(e) => setData('date_of_birth', e.target.value)} className="field-input" />
                    {errors.date_of_birth && <p className="field-hint text-ink-expl">{errors.date_of_birth}</p>}
                </div>
                <label className="flex items-start gap-2.5 text-[13px] leading-snug text-ink-muted">
                    <input
                        type="checkbox"
                        checked={data.age_confirmed}
                        onChange={(e) => setData('age_confirmed', e.target.checked)}
                        className="mt-0.5 h-5 w-5 shrink-0 rounded border-ink-line bg-ink-raised text-ink-brand focus:ring-ink-brand"
                    />
                    <span>I am 18 or older and I have read the terms, privacy policy and content policy.</span>
                </label>
                {errors.age_confirmed && <p className="field-hint text-ink-expl">{errors.age_confirmed}</p>}

                <button type="submit" disabled={processing} className="btn w-full min-h-[50px] rounded-xl bg-gradient-to-br from-ink-brand to-ink-pink text-[15px] text-[#170d13]">
                    Create account
                </button>
            </form>

            <p className="mt-4 text-center text-[13.5px] text-ink-muted">
                Already have an account?{' '}
                <Link href={route('login')} className="font-semibold text-ink-brand hover:underline">
                    Log in
                </Link>
            </p>
        </GuestLayout>
    );
}
