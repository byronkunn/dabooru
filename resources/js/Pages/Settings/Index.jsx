import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { useState } from 'react';

const SECTIONS = [
    ['profile', 'Profile'],
    ['content', 'Content & feed'],
    ['notifications', 'Notifications'],
    ['account', 'Account & security'],
];

const TOGGLES = {
    content: [
        ['show_explicit', 'Show explicit work', 'When off, explicit posts are hidden from your gallery and feed.'],
    ],
    notifications: [
        ['notification_likes', 'Likes', null],
        ['notification_comments', 'Comments', null],
        ['notification_follows', 'New followers', null],
    ],
};

function Toggle({ checked, onChange, label, description }) {
    return (
        <div className="flex min-h-[58px] items-center gap-4 border-b border-ink-line py-3.5 last:border-b-0">
            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold">{label}</p>
                {description && <p className="mt-0.5 text-[12.5px] leading-snug text-ink-muted">{description}</p>}
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-label={label}
                onClick={() => onChange(!checked)}
                className={`relative h-5 w-9 shrink-0 rounded-full transition-colors ${checked ? 'bg-ink-brand/40' : 'bg-[#3a3242]'}`}
            >
                <span className={`absolute top-0.5 h-4 w-4 rounded-full transition-all ${checked ? 'left-[18px] bg-ink-brand' : 'left-0.5 bg-[#8d8297]'}`} />
            </button>
        </div>
    );
}

export default function SettingsIndex({ user, settings, demoAccounts = [] }) {
    const profileForm = useForm({
        name: user.name,
        username: user.username,
        bio: user.bio ?? '',
        location: user.location ?? '',
        website: user.website ?? '',
        avatar: null,
        banner: null,
    });
    const settingsForm = useForm({ settings });
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const deleteForm = useForm({
        password: '',
    });
    const [confirmingDeletion, setConfirmingDeletion] = useState(false);

    const updatePassword = (e) => {
        e.preventDefault();
        passwordForm.put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    };

    const deleteAccount = (e) => {
        e.preventDefault();
        deleteForm.delete(route('settings.account.destroy'), {
            preserveScroll: true,
        });
    };

    const saveProfile = (e) => {
        e.preventDefault();
        profileForm.post(route('settings.profile'), { preserveScroll: true, forceFormData: true });
    };

    const saveSettings = (patch) => {
        const next = { ...settingsForm.data.settings, ...patch };
        settingsForm.setData('settings', next);
        settingsForm.post(route('settings.update'), { preserveScroll: true, onSuccess: () => settingsForm.setData('settings', next) });
    };

    return (
        <AppLayout>
            <Head title="Settings" />
            <TopBar title="Settings" back={route('profile.show', user.username)} />

            <div className="grid gap-3.5 p-3.5 md:grid-cols-[220px_minmax(0,1fr)] md:gap-6 md:p-5">
                <nav className="hidden flex-col gap-0.5 md:flex" aria-label="Settings sections">
                    {SECTIONS.map(([key, label], i) => (
                        <a key={key} href={`#${key}`} className={`flex min-h-11 items-center rounded-[10px] px-3 text-sm ${i === 0 ? 'bg-ink-raised font-semibold text-ink-brand' : 'text-ink-muted hover:bg-ink-raised hover:text-ink-text'}`}>
                            {label}
                        </a>
                    ))}
                </nav>

                <div className="min-w-0">
                    <section id="profile" className="panel mb-4">
                        <h2 className="text-[17px] font-bold">Profile</h2>
                        <p className="mb-4 mt-1 text-[13px] text-ink-muted">How you appear across the site.</p>
                        <form onSubmit={saveProfile} className="space-y-3.5">
                            <div className="grid gap-3.5 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="name" className="field-label">Display name</label>
                                    <input id="name" value={profileForm.data.name} onChange={(e) => profileForm.setData('name', e.target.value)} className="field-input" maxLength={60} />
                                    {profileForm.errors.name && <p className="field-hint text-ink-expl">{profileForm.errors.name}</p>}
                                </div>
                                <div>
                                    <label htmlFor="username" className="field-label">Handle</label>
                                    <input id="username" value={profileForm.data.username} onChange={(e) => profileForm.setData('username', e.target.value)} className="field-input" maxLength={30} />
                                    {profileForm.errors.username && <p className="field-hint text-ink-expl">{profileForm.errors.username}</p>}
                                </div>
                            </div>
                            <div>
                                <label htmlFor="bio" className="field-label">Bio</label>
                                <textarea id="bio" rows={3} maxLength={240} value={profileForm.data.bio} onChange={(e) => profileForm.setData('bio', e.target.value)} className="field-input resize-none" />
                            </div>
                            <div className="grid gap-3.5 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="location" className="field-label">Location</label>
                                    <input id="location" value={profileForm.data.location} onChange={(e) => profileForm.setData('location', e.target.value)} className="field-input" />
                                </div>
                                <div>
                                    <label htmlFor="website" className="field-label">Website</label>
                                    <input id="website" value={profileForm.data.website} onChange={(e) => profileForm.setData('website', e.target.value)} className="field-input" />
                                </div>
                            </div>
                            <div className="grid gap-3.5 sm:grid-cols-2">
                                <div>
                                    <label className="field-label">Avatar</label>
                                    <input type="file" accept="image/*" onChange={(e) => profileForm.setData('avatar', e.target.files[0])} className="field-input py-2 text-[13px]" />
                                </div>
                                <div>
                                    <label className="field-label">Banner</label>
                                    <input type="file" accept="image/*" onChange={(e) => profileForm.setData('banner', e.target.files[0])} className="field-input py-2 text-[13px]" />
                                </div>
                            </div>
                            <div className="flex justify-end">
                                <button type="submit" disabled={profileForm.processing} className="btn-primary">
                                    Save profile
                                </button>
                            </div>
                        </form>
                    </section>

                    {Object.entries(TOGGLES).map(([key, rows]) => (
                        <section key={key} id={key} className="panel mb-4">
                            <h2 className="text-[17px] font-bold capitalize">{SECTIONS.find(([k]) => k === key)?.[1]}</h2>
                            <div className="mt-2">
                                {rows.map(([settingKey, label, description]) => (
                                    <Toggle
                                        key={settingKey}
                                        label={label}
                                        description={description}
                                        checked={Boolean(settingsForm.data.settings[settingKey])}
                                        onChange={(value) => saveSettings({ [settingKey]: value })}
                                    />
                                ))}
                            </div>
                        </section>
                    ))}

                    <section className="panel mb-4">
                        <h2 className="text-[17px] font-bold">Blocked accounts</h2>
                        <p className="mb-3 mt-1 text-[13px] text-ink-muted">
                            People you blocked cannot appear in your feed or message you.
                        </p>
                        <Link href={route('blocks.index')} className="btn-ghost">
                            Manage blocked accounts
                        </Link>
                    </section>

                    {demoAccounts.length > 0 && (
                        <section className="panel mb-4">
                            <h2 className="text-[17px] font-bold">Demo accounts</h2>
                            <p className="mb-3 mt-1 text-[13px] text-ink-muted">
                                Local development only. Jump straight into another account.
                            </p>
                            <div className="space-y-1.5">
                                {demoAccounts.map((account) => (
                                    <a
                                        key={account.username}
                                        href={account.url}
                                        className="flex items-center gap-2.5 rounded-xl px-2 py-2 hover:bg-ink-raised"
                                    >
                                        <img src={account.avatar} alt="" className="h-8 w-8 rounded-full bg-ink-raised object-cover" />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-[13.5px] font-bold">{account.name}</span>
                                            <span className="block truncate text-[11.5px] text-ink-muted">{account.role}</span>
                                        </span>
                                        <span className="text-[12px] font-bold text-ink-brand">Switch</span>
                                    </a>
                                ))}
                            </div>
                        </section>
                    )}

                    <section id="account" className="panel">
                        <h2 className="text-[17px] font-bold">Account & security</h2>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            Signed in as <span className="font-semibold text-ink-text">{user.email}</span>.
                        </p>

                        <div className="mt-6 border-t border-ink-line pt-5">
                            <h3 className="text-sm font-semibold">Change password</h3>
                            <p className="mb-3.5 mt-0.5 text-[12.5px] text-ink-muted">
                                Use a strong password with at least 8 characters.
                            </p>
                            <form onSubmit={updatePassword} className="max-w-md space-y-3">
                                <div>
                                    <label htmlFor="current_password" className="field-label">Current password</label>
                                    <input
                                        id="current_password"
                                        type="password"
                                        value={passwordForm.data.current_password}
                                        onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                        className="field-input"
                                        autoComplete="current-password"
                                        required
                                    />
                                    {passwordForm.errors.current_password && (
                                        <p className="field-hint text-ink-expl">{passwordForm.errors.current_password}</p>
                                    )}
                                </div>
                                <div>
                                    <label htmlFor="password" className="field-label">New password</label>
                                    <input
                                        id="password"
                                        type="password"
                                        value={passwordForm.data.password}
                                        onChange={(e) => passwordForm.setData('password', e.target.value)}
                                        className="field-input"
                                        autoComplete="new-password"
                                        required
                                    />
                                    {passwordForm.errors.password && (
                                        <p className="field-hint text-ink-expl">{passwordForm.errors.password}</p>
                                    )}
                                </div>
                                <div>
                                    <label htmlFor="password_confirmation" className="field-label">Confirm new password</label>
                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        value={passwordForm.data.password_confirmation}
                                        onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                        className="field-input"
                                        autoComplete="new-password"
                                        required
                                    />
                                </div>
                                <div className="flex items-center gap-3">
                                    <button type="submit" disabled={passwordForm.processing} className="btn-primary">
                                        Update password
                                    </button>
                                    {passwordForm.recentlySuccessful && (
                                        <span className="text-xs text-ink-sfw">Saved.</span>
                                    )}
                                </div>
                            </form>
                        </div>

                        <div className="mt-8 border-t border-ink-line pt-5">
                            <h3 className="text-sm font-semibold text-[#ff98a9]">Delete account</h3>
                            <p className="mb-3.5 mt-0.5 text-[12.5px] text-ink-muted">
                                Once deleted, all of your works, comics, and comments will be permanently removed.
                            </p>
                            {!confirmingDeletion ? (
                                <button
                                    type="button"
                                    onClick={() => setConfirmingDeletion(true)}
                                    className="btn-ghost border-[#6b2c3a] text-[#ff98a9] hover:bg-[#ff98a9]/10"
                                >
                                    Delete my account
                                </button>
                            ) : (
                                <form onSubmit={deleteAccount} className="max-w-md rounded-xl border border-[#6b2c3a]/60 bg-[#201015] p-4 space-y-3">
                                    <p className="text-xs font-semibold text-[#ff98a9]">
                                        Please enter your password to confirm account deletion:
                                    </p>
                                    <input
                                        type="password"
                                        value={deleteForm.data.password}
                                        onChange={(e) => deleteForm.setData('password', e.target.value)}
                                        placeholder="Your password"
                                        className="field-input"
                                        required
                                    />
                                    {deleteForm.errors.password && (
                                        <p className="field-hint text-ink-expl">{deleteForm.errors.password}</p>
                                    )}
                                    <div className="flex gap-2">
                                        <button
                                            type="submit"
                                            disabled={deleteForm.processing}
                                            className="btn bg-[#ff536e] text-white hover:bg-[#ff3b59]"
                                        >
                                            Confirm permanent delete
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setConfirmingDeletion(false)}
                                            className="btn-ghost"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
