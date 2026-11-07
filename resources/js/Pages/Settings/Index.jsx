import { Head, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';

const SECTIONS = [
    ['profile', 'Profile'],
    ['content', 'Content & feed'],
    ['media', 'Media'],
    ['notifications', 'Notifications'],
    ['privacy', 'Privacy'],
];

const TOGGLES = {
    content: [
        ['show_explicit', 'Show explicit work', 'When off, explicit posts are hidden from your gallery and feed.'],
        ['mature_preview', 'Mature preview', 'Show a small preview on gated content instead of a blur.'],
    ],
    media: [
        ['autoplay_videos', 'Autoplay videos in feeds', 'Off saves data on mobile connections.'],
        ['animate_gifs', 'Animate GIFs', 'GIFs play on hover, never automatically in the grid.'],
        ['reduce_motion', 'Reduce motion', 'Disables hover lifts and transitions.'],
    ],
    notifications: [
        ['notification_likes', 'Likes', null],
        ['notification_comments', 'Comments', null],
        ['notification_follows', 'New followers', null],
        ['email_notifications', 'Email notifications', 'A weekly digest, nothing more.'],
    ],
    privacy: [
        ['dm_requests', 'Message requests', "People you don't follow can send a request."],
        ['read_receipts', 'Read receipts', 'Show when you have read a message.'],
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

                    <section className="panel">
                        <h2 className="text-[17px] font-bold">Account</h2>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            Signed in as <span className="text-ink-text">{user.email}</span>.
                        </p>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
