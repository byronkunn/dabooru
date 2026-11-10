import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { Icon } from '@/Components/Icons';
import { useEffect, useState } from 'react';
import { Errors } from '@/Components/KnowledgeUi';
import ProfileImageDialog from '@/Components/ProfileImageDialog';

const SECTIONS = [
    ['account', 'Your account', 'Account information, password, and account deletion.', 'user'],
    ['profile', 'Edit profile', 'Your name, bio, avatar, and banner.', 'user'],
    ['content', 'Privacy and content', 'Control the work you see in your feed.', 'eye'],
    ['notifications', 'Notifications', 'Choose which activity you hear about.', 'bell'],
    ['mutes', 'Muted tags', 'Tags hidden from every feed and from search.', 'tag'],
    ['reports', 'Your reports', 'Where the reports you filed stand.', 'flag'],
    ['blocked', 'Blocked accounts', 'Manage the people you have blocked.', 'settings'],
];

const TOGGLES = {
    content: [
        ['show_explicit', 'Show explicit work', 'When off, explicit posts are hidden from your gallery and feed.'],
    ],
    notifications: [
        ['notification_likes', 'Likes', null],
        ['notification_comments', 'Comments', null],
        ['notification_follows', 'New followers', null],
        ['notification_new_work', 'New work from artists I follow', 'Adds a notification when an artist you follow publishes. Explicit work follows your content visibility setting.'],
    ],
};

function Toggle({ checked, onChange, label, description, disabled }) {
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
                disabled={disabled}
                onClick={() => onChange(!checked)}
                className={`relative h-5 w-9 shrink-0 rounded-full transition-colors ${checked ? 'bg-ink-brand/40' : 'bg-[#3a3242]'}`}
            >
                <span className={`absolute top-0.5 h-4 w-4 rounded-full transition-all ${checked ? 'left-[18px] bg-ink-brand' : 'left-0.5 bg-[#8d8297]'}`} />
            </button>
        </div>
    );
}

function MutedTags({ tags, suggestions, onAdd, onRemove, busy }) {
    const [text, setText] = useState('');
    const [open, setOpen] = useState(false);

    const commit = (raw) => {
        const clean = raw.trim().toLowerCase().replace(/^#/, '').replace(/\s+/g, ' ').slice(0, 40);
        if (clean) onAdd(clean);
        setText('');
    };

    const matches = suggestions
        .filter((t) => !tags.includes(t))
        .filter((t) => (text.trim() ? t.includes(text.trim().toLowerCase()) : true))
        .slice(0, 6);

    return (
        <div>
            {tags.length === 0 ? (
                <p className="mb-2 rounded-xl border border-dashed border-ink-line px-3 py-2.5 text-[12.5px] text-ink-faint">
                    No muted tags yet.
                </p>
            ) : (
                <ul className="mb-2 flex flex-wrap gap-1.5">
                    {tags.map((tag) => (
                        <li key={tag} className="inline-flex items-center gap-1 rounded-full border border-ink-line bg-ink-raised px-2.5 py-1 text-[12.5px] font-semibold">
                            #{tag}
                            <button type="button" disabled={busy} onClick={() => onRemove(tag)} aria-label={`Unmute ${tag}`} className="opacity-60 hover:opacity-100">
                                <Icon name="close" className="h-3 w-3" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <div className="relative">
                <input
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    onFocus={() => setOpen(true)}
                    onBlur={() => setTimeout(() => setOpen(false), 120)}
                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); commit(text); } }}
                    placeholder="Mute a tag, then Enter"
                    aria-label="Mute a tag"
                    className="field-input min-h-[40px] w-full text-[13.5px]"
                />
                {open && matches.length > 0 && (
                    <div className="absolute left-0 right-0 top-full z-10 mt-1 overflow-hidden rounded-xl border border-ink-line bg-ink-surface shadow-xl">
                        {matches.map((t) => (
                            <button key={t} type="button" onMouseDown={(e) => { e.preventDefault(); commit(t); }}
                                className="block w-full px-3 py-1.5 text-left text-[13px] text-ink-text hover:bg-ink-raised">
                                #{t}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

export default function SettingsIndex({ user, settings, messagingPrivacy, mutedTags = [], mutedTagSuggestions = [], demoAccounts = [], imageLimits = {} }) {
    const muteForm = useForm({ tag: '' });
    const profileForm = useForm(`SettingsProfile:${user.id}`, {
        name: user.name,
        username: user.username,
        bio: user.bio ?? '',
        location: user.location ?? '',
        website: user.website ?? '',
        ai_disclosure: user.ai_disclosure ?? 'none',
    });
    const settingsForm = useForm({ settings });
    const privacyForm = useForm(messagingPrivacy);
    const emailForm = useForm({ email: user.email, current_password: '' });
    const verificationForm = useForm({});
    const [editingImage, setEditingImage] = useState(null);
    useEffect(() => {
        settingsForm.setData('settings', settings);
    }, [settings]);
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const deleteForm = useForm({
        password: '',
    });
    const [confirmingDeletion, setConfirmingDeletion] = useState(false);
    const sections = [...SECTIONS, ...(demoAccounts.length ? [['demo', 'Demo accounts', 'Switch accounts for local testing.', 'user']] : [])];
    const readSection = () => {
        const key = window.location.hash.slice(1);
        return sections.some(([id]) => id === key) ? key : null;
    };
    const [section, setSection] = useState(readSection);
    useEffect(() => {
        const sync = () => setSection(readSection());
        window.addEventListener('hashchange', sync);
        window.addEventListener('popstate', sync);
        return () => {
            window.removeEventListener('hashchange', sync);
            window.removeEventListener('popstate', sync);
        };
    }, [demoAccounts.length]);
    const activeSection = section ?? 'account';
    const selectSection = (key) => {
        router.push({
            url: window.location.pathname + window.location.search + (key ? `#${key}` : ''),
            preserveState: true,
            preserveScroll: true,
        });
        setSection(key);
    };
    const sectionClass = (key) => `${activeSection === key ? '' : 'hidden'} border-b border-ink-line p-4 md:p-5`;

    const updatePassword = (e) => {
        e.preventDefault();
        passwordForm.put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => { passwordForm.reset(); selectSection('account'); },
            onError: () => selectSection('account'),
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
        profileForm.post(route('settings.profile'), {
            preserveScroll: true, forceFormData: true,
            onSuccess: () => {
                selectSection('profile');
            },
            onError: () => selectSection('profile'),
        });
    };

    const saveSettings = (patch) => {
        const previous = settingsForm.data.settings;
        settingsForm.setData('settings', { ...previous, ...patch });
        settingsForm.transform(() => ({ settings: patch }));
        settingsForm.post(route('settings.update'), {
            preserveScroll: true,
            onSuccess: () => selectSection(activeSection),
            onError: () => { settingsForm.setData('settings', previous); selectSection(activeSection); },
        });
    };

    return (
        <AppLayout rightRail={false}>
            <Head title="Settings" />
            <TopBar title="Settings" back={route('profile.show', user.username)} />

            <div className="min-h-[70vh] md:grid md:grid-cols-[minmax(220px,2fr)_minmax(0,3fr)]">
                <nav className={`${section ? 'hidden' : 'flex'} flex-col border-ink-line md:flex md:border-r`} aria-label="Settings sections">
                    <p className="px-4 py-4 text-sm text-ink-muted">Manage your account, privacy, and preferences.</p>
                    {sections.map(([key, label, description, icon]) => (
                        <button key={key} type="button" onClick={() => selectSection(key)} aria-current={section === key ? 'page' : undefined}
                            className={`flex min-h-20 items-center gap-3 border-r-2 px-4 py-4 text-left hover:bg-ink-raised ${activeSection === key ? 'border-ink-brand md:bg-ink-raised' : 'border-transparent'}`}>
                            <Icon name={icon} className="h-5 w-5 shrink-0 text-ink-muted" />
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-bold">{label}</span>
                                <span className="mt-1 block text-xs leading-relaxed text-ink-muted">{description}</span>
                            </span>
                            <Icon name="back" className="h-4 w-4 rotate-180 text-ink-muted" />
                        </button>
                    ))}
                </nav>

                <div className={`${section ? 'block' : 'hidden'} min-w-0 md:block`}>
                    <button type="button" onClick={() => selectSection(null)} className="flex min-h-12 items-center gap-2 px-4 text-sm font-bold text-ink-brand md:hidden">
                        <Icon name="back" className="h-4 w-4" /> All settings
                    </button>
                    <section id="profile" className={sectionClass('profile')}>
                        <h2 className="text-[17px] font-bold">Profile</h2>
                        <p className="mb-4 mt-1 text-[13px] text-ink-muted">How you appear across the site.</p>
                        <form onSubmit={saveProfile} className="space-y-3.5">
                            <div className="grid gap-3.5 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="name" className="field-label">Display name</label>
                                    <input id="name" required value={profileForm.data.name} onChange={(e) => profileForm.setData('name', e.target.value)} className="field-input" maxLength={60} />
                                    {profileForm.errors.name && <p className="field-hint text-ink-expl">{profileForm.errors.name}</p>}
                                </div>
                                <div>
                                    <label htmlFor="username" className="field-label">Handle</label>
                                    <input id="username" required minLength={3} value={profileForm.data.username} onChange={(e) => profileForm.setData('username', e.target.value)} className="field-input" maxLength={30} />
                                    {profileForm.errors.username && <p className="field-hint text-ink-expl">{profileForm.errors.username}</p>}
                                </div>
                            </div>
                            <div>
                                <label htmlFor="ai_disclosure" className="field-label">AI disclosure</label>
                                <select id="ai_disclosure" value={profileForm.data.ai_disclosure} onChange={(e) => profileForm.setData('ai_disclosure', e.target.value)} className="field-input">
                                    <option value="none">No AI disclosure</option>
                                    <option value="ai_artist">I am an AI artist</option>
                                    <option value="ai_works">Some of my works are AI-generated</option>
                                    <option value="both">I am an AI artist and my works are AI-generated</option>
                                </select>
                                <p className="mt-1 text-xs text-ink-muted">When selected, this disclosure appears publicly on your profile.</p>
                            </div>
                            <div>
                                <label htmlFor="bio" className="field-label">Bio</label>
                                <textarea id="bio" rows={3} maxLength={240} value={profileForm.data.bio} onChange={(e) => profileForm.setData('bio', e.target.value)} className="field-input resize-none" />
                            </div>
                            <div className="grid gap-3.5 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="location" className="field-label">Location</label>
                                    <input id="location" maxLength={80} value={profileForm.data.location} onChange={(e) => profileForm.setData('location', e.target.value)} className="field-input" />
                                </div>
                                <div>
                                    <label htmlFor="website" className="field-label">Website</label>
                                    <input id="website" type="url" maxLength={120} placeholder="https://example.com" value={profileForm.data.website} onChange={(e) => profileForm.setData('website', e.target.value)} className="field-input" />
                                </div>
                            </div>
                            <div className="grid gap-3.5 sm:grid-cols-2">
                                <div className="rounded-xl border border-ink-line p-3">
                                    <p className="field-label">Profile avatar</p>
                                    <img src={user.avatar} alt="Current avatar" className="mb-3 h-20 w-20 rounded-full object-cover" />
                                    <button type="button" onClick={() => setEditingImage('avatar')} className="btn-ghost min-h-11">Edit avatar</button>
                                </div>
                                <div className="rounded-xl border border-ink-line p-3">
                                    <p className="field-label">Profile banner</p>
                                    {user.banner ? <img src={user.banner} alt="Current banner" className="mb-3 aspect-[3/1] w-full rounded-lg object-cover" /> : <div className="mb-3 aspect-[3/1] w-full rounded-lg bg-gradient-to-br from-[#3a2740] via-[#1d1a24] to-[#3b2a1c]" />}
                                    <button type="button" onClick={() => setEditingImage('banner')} className="btn-ghost min-h-11">Edit banner</button>
                                </div>
                            </div>
                            <Errors errors={Object.fromEntries(Object.entries(profileForm.errors).filter(([key]) => !['name', 'username'].includes(key)))} />
                            <div className="flex items-center justify-end gap-3">
                                {profileForm.recentlySuccessful && <p role="status" className="text-xs text-ink-sfw">Profile saved.</p>}
                                <button type="submit" disabled={profileForm.processing} className="btn-primary">
                                    Save profile
                                </button>
                            </div>
                        </form>
                    </section>

                    {Object.entries(TOGGLES).map(([key, rows]) => (
                        <section key={key} id={key} className={sectionClass(key)}>
                            <h2 className="text-[17px] font-bold capitalize">{SECTIONS.find(([k]) => k === key)?.[1]}</h2>
                            <div className="mt-2">
                                {rows.map(([settingKey, label, description]) => (
                                    <Toggle
                                        key={settingKey}
                                        label={label}
                                        description={description}
                                        checked={Boolean(settingsForm.data.settings[settingKey])}
                                        disabled={settingsForm.processing}
                                        onChange={(value) => saveSettings({ [settingKey]: value })}
                                    />
                                ))}
                            </div>
                            <Errors errors={settingsForm.errors} />
                            {settingsForm.recentlySuccessful && <p role="status" className="mt-2 text-xs text-ink-sfw">Saved.</p>}
                            {key === 'content' && <form className="mt-6 space-y-3 border-t border-ink-line pt-4" onSubmit={(event) => {
                                event.preventDefault();
                                privacyForm.put(route('messages.privacy'), { preserveScroll: true, onSuccess: () => selectSection('content'), onError: () => selectSection('content') });
                            }}>
                                <h3 className="font-semibold">Messaging privacy</h3>
                                <label className="block text-sm">Who can start a direct message?<select className="field-input mt-1" value={privacyForm.data.chat_dm_permission} onChange={(event) => privacyForm.setData('chat_dm_permission', event.target.value)}>
                                    <option value="anyone">Anyone</option><option value="following">People I follow</option><option value="nobody">Nobody</option>
                                </select></label>
                                <Toggle checked={privacyForm.data.chat_read_receipts} onChange={(value) => privacyForm.setData('chat_read_receipts', value)} label="Read receipts" description="Visible only when both participants enable them." disabled={privacyForm.processing} />
                                <Toggle checked={privacyForm.data.chat_allow_forwarding} onChange={(value) => privacyForm.setData('chat_allow_forwarding', value)} label="Allow forwarding" description="Allow other participants to forward your messages." disabled={privacyForm.processing} />
                                <Errors errors={privacyForm.errors} />
                                <div className="flex items-center gap-3"><button className="btn-primary" disabled={privacyForm.processing}>Save messaging privacy</button>{privacyForm.recentlySuccessful && <span role="status" className="text-xs text-ink-sfw">Saved.</span>}</div>
                            </form>}
                        </section>
                    ))}

                    <section className={sectionClass('mutes')}>
                        <h2 className="text-[17px] font-bold">Muted tags</h2>
                        <p className="mb-3 mt-1 text-[13px] text-ink-muted">
                            Works with these tags are hidden from Browse, Discover, Following, Library and search.
                            Mutes follow your account, so they apply on every device.
                        </p>

                        <MutedTags
                            tags={mutedTags ?? []}
                            suggestions={mutedTagSuggestions ?? []}
                            onAdd={(tag) => {
                                muteForm.transform(() => ({ tag }));
                                muteForm.post(route('settings.muted-tags.store'), {
                                    preserveScroll: true,
                                    onSuccess: () => muteForm.reset(),
                                });
                            }}
                            onRemove={(tag) => {
                                muteForm.transform(() => ({ tag }));
                                muteForm.delete(route('settings.muted-tags.destroy'), { preserveScroll: true });
                            }}
                            busy={muteForm.processing}
                        />
                        <Errors errors={muteForm.errors} />
                    </section>

                    <section className={sectionClass('reports')}>
                        <h2 className="text-[17px] font-bold">Your reports</h2>
                        <p className="mb-3 mt-1 text-[13px] text-ink-muted">
                            See where each report you filed stands: received, under review, or closed.
                        </p>
                        <Link href={route('reports.index')} className="btn-ghost">
                            View your reports
                        </Link>
                    </section>

                    <section className={sectionClass('blocked')}>
                        <h2 className="text-[17px] font-bold">Blocked accounts</h2>
                        <p className="mb-3 mt-1 text-[13px] text-ink-muted">
                            People you blocked cannot appear in your feed or message you.
                        </p>
                        <Link href={route('blocks.index')} className="btn-ghost">
                            Manage blocked accounts
                        </Link>
                    </section>

                    {demoAccounts.length > 0 && (
                        <section className={sectionClass('demo')}>
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

                    <section id="account" className={sectionClass('account')}>
                        <h2 className="text-[17px] font-bold">Account & security</h2>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            Signed in as <span className="font-semibold text-ink-text">{user.email}</span>.
                        </p>

                        <form className="mt-6 max-w-md space-y-3 border-t border-ink-line pt-5" onSubmit={(event) => {
                            event.preventDefault();
                            emailForm.put(route('settings.email'), { preserveScroll: true, onSuccess: () => { emailForm.reset('current_password'); selectSection('account'); }, onError: () => selectSection('account') });
                        }}>
                            <h3 className="text-sm font-semibold">Email address</h3>
                            <label className="block text-sm">Email<input type="email" required maxLength={255} autoComplete="email" className="field-input mt-1" value={emailForm.data.email} onChange={(event) => emailForm.setData('email', event.target.value)} /></label>
                            <label className="block text-sm">Current password to change email<input type="password" required autoComplete="current-password" className="field-input mt-1" value={emailForm.data.current_password} onChange={(event) => emailForm.setData('current_password', event.target.value)} /></label>
                            <Errors errors={emailForm.errors} />
                            <button className="btn-primary" disabled={emailForm.processing}>Save email</button>
                            {emailForm.recentlySuccessful && <p role="status" className="text-xs text-ink-sfw">Email saved.</p>}
                        </form>
                        <div className="mt-3 text-sm">
                            <p className="text-ink-muted">{user.email_verified ? 'Email verified.' : 'Your email is not verified. Verify it before contributing.'}</p>
                            {!user.email_verified && <button type="button" className="btn-ghost mt-2" disabled={verificationForm.processing} onClick={() => verificationForm.post(route('verification.send'), { preserveScroll: true, onSuccess: () => selectSection('account') })}>Resend verification email</button>}
                            <Errors errors={verificationForm.errors} />
                            {verificationForm.recentlySuccessful && <p role="status" className="mt-2 text-xs text-ink-sfw">Verification email sent.</p>}
                        </div>

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
                                This permanently closes your account and removes your works, comics, and comments from the site. Files and moderation records may be retained where required by our privacy policy.
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
            {editingImage && <ProfileImageDialog
                key={editingImage}
                type={editingImage}
                current={user[`${editingImage}Source`]}
                canRemove={Boolean(user[`${editingImage}Source`])}
                avatarDisplay={user.avatar}
                bannerDisplay={user.banner}
                maxMb={editingImage === 'avatar' ? imageLimits.avatar_mb : imageLimits.banner_mb}
                onClose={() => setEditingImage(null)}
            />}
        </AppLayout>
    );
}
