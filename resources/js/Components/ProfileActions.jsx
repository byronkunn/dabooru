import { Dialog, DialogPanel, DialogTitle, Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Icon } from '@/Components/Icons';
import { Errors } from '@/Components/KnowledgeUi';

export default function ProfileActions({ profile, following, relationship, hasStickerPacks }) {
    const [confirmation, setConfirmation] = useState(null);
    const form = useForm({});
    const blocked = relationship.blocked;
    const act = (action) => {
        form.clearErrors();
        const options = { preserveScroll: true, onSuccess: () => setConfirmation(null) };
        if (action === 'follow') form.post(route('users.follow', profile.username), options);
        if (action === 'unfollow') form.delete(route('users.unfollow', profile.username), options);
        if (action === 'block') form.post(route('users.block', profile.username), options);
        if (action === 'unblock') form.delete(route('users.unblock', profile.username), options);
    };
    const confirm = (action) => {
        form.clearErrors();
        setConfirmation(action);
    };
    const titles = {
        unfollow: `Unfollow @${profile.username}?`,
        block: `Block @${profile.username}?`,
        unblock: `Unblock @${profile.username}?`,
    };
    const descriptions = {
        unfollow: 'Their new work will no longer appear in your Following feed. You can follow them again at any time.',
        block: 'You will stop following each other. You will not be able to follow or message each other while blocked, and their work will be hidden from your feeds. They will not be notified.',
        unblock: 'You will be able to follow and message each other again, subject to message privacy settings. Previous follows will not be restored.',
    };

    return (
        <div className="col-span-2 min-w-0">
            <div className="flex items-center justify-end gap-2">
                <Menu>
                    <MenuButton aria-label="More profile actions" className="btn-ghost h-11 w-11 rounded-full !p-0" disabled={form.processing}><Icon name="more" /></MenuButton>
                    <MenuItems anchor="bottom end" className="z-40 w-56 max-w-[calc(100vw-2rem)] rounded-xl border border-ink-line bg-ink-surface p-1 shadow-xl [--anchor-gap:8px]">
                        {hasStickerPacks && <MenuItem><Link href={route('messages.stickers.index', { artist: profile.username })} className="block rounded-lg px-3 py-3 text-sm data-[focus]:bg-ink-raised">Sticker packs</Link></MenuItem>}
                        <MenuItem><button type="button" onClick={() => confirm(blocked ? 'unblock' : 'block')} className="w-full rounded-lg px-3 py-3 text-left text-sm font-semibold text-red-300 data-[focus]:bg-ink-raised">{blocked ? 'Unblock' : 'Block'} @{profile.username}</button></MenuItem>
                    </MenuItems>
                </Menu>
                {relationship.canMessage && (
                    <Link href={route('messages.index', { to: profile.username })} aria-label={`Message @${profile.username}`} title="Message" className="btn-ghost h-11 w-11 rounded-full !p-0"><Icon name="chat" /></Link>
                )}
                {blocked ? (
                    <button type="button" onClick={() => confirm('unblock')} disabled={form.processing} className="btn-ghost min-h-11 rounded-full border-red-400/50 text-red-300">Blocked</button>
                ) : relationship.canFollow && (
                    <button type="button" disabled={form.processing} onClick={() => following ? confirm('unfollow') : act('follow')} aria-label={following ? `Unfollow @${profile.username}` : `Follow @${profile.username}`} className={`${following ? 'btn-ghost' : 'btn-primary'} min-h-11 min-w-28 rounded-full`}>
                        {form.processing && !confirmation ? 'Saving...' : following ? 'Unfollow' : 'Follow'}
                    </button>
                )}
            </div>
            {relationship.followsYou && !blocked && !relationship.blockedBy && <p className="mt-2 text-right text-xs text-ink-muted">Follows you</p>}
            {blocked && <p className="mt-2 text-sm text-ink-muted">You blocked this account. Unblock to follow or message them.</p>}
            {!blocked && relationship.blockedBy && <p className="mt-2 text-sm text-ink-muted">You cannot follow or message this account.</p>}
            {!blocked && !relationship.blockedBy && !relationship.canMessage && <p className="mt-2 text-right text-xs text-ink-muted">This account is not accepting new messages.</p>}
            {!confirmation && <Errors errors={form.errors} />}
            {confirmation && (
                <Dialog open onClose={() => !form.processing && setConfirmation(null)} className="fixed inset-0 z-[80]">
                    <div className="fixed inset-0 bg-black/70" aria-hidden="true" />
                    <div className="fixed inset-0 flex items-center justify-center p-4">
                        <DialogPanel className="w-full max-w-sm rounded-2xl border border-ink-line bg-ink-surface p-5">
                            <DialogTitle className="break-words text-lg font-bold">{titles[confirmation]}</DialogTitle>
                            <p className="mt-3 text-sm text-ink-muted">{descriptions[confirmation]}</p>
                            <Errors errors={form.errors} />
                            <button type="button" onClick={() => act(confirmation)} disabled={form.processing} className="btn-primary mt-5 min-h-11 w-full rounded-full">{form.processing ? 'Saving...' : confirmation === 'unfollow' ? 'Unfollow' : confirmation === 'block' ? 'Block' : 'Unblock'}</button>
                            <button type="button" onClick={() => setConfirmation(null)} disabled={form.processing} className="btn-ghost mt-2 min-h-11 w-full rounded-full">Cancel</button>
                        </DialogPanel>
                    </div>
                </Dialog>
            )}
        </div>
    );
}
