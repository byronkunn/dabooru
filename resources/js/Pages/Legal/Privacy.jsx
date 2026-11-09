import { LegalPage, H2, P, UL } from '@/Components/LegalPage';

export default function Privacy({ updated }) {
    return (
        <LegalPage
            title="Privacy policy"
            updated={updated}
            intro={
                <>
                    Here is what this site actually collects today. It should be reviewed by a lawyer for
                    your jurisdiction (GDPR, CCPA and similar) before you open it publicly.
                </>
            }
        >
            <section className="space-y-3">
                <H2>1. What we store</H2>
                <UL>
                    <li><strong className="text-ink-text">Account:</strong> email address, display name, handle, password hash, date of birth (for the age check), and anything you put in your profile.</li>
                    <li><strong className="text-ink-text">Activity:</strong> posts you upload, likes, comments, follows, bookmarks, reading position, and messages you send.</li>
                    <li><strong className="text-ink-text">Technical:</strong> IP address and user agent in server logs and the session, for security and abuse prevention.</li>
                    <li><strong className="text-ink-text">Views:</strong> a per-day record that you viewed a post, stored as your user id (or a hash for logged-out visitors) so a view is counted once.</li>
                    <li><strong className="text-ink-text">Creator analytics:</strong> a referring-page category (such as Gallery, Discover, or tags), follow/unfollow counts, and comic pages reached. Guest comic visits use a hashed session identifier. Creators receive aggregate counts, not viewer identities or external referring URLs.</li>
                    <li><strong className="text-ink-text">Studio planner:</strong> private ideas, notes, deadlines, and publishing schedules you save. Scheduled drafts become public when published.</li>
                    <li><strong className="text-ink-text">Collections:</strong> folder names, saved-work membership, and any share links you enable.</li>
                </UL>
            </section>

            <section className="space-y-3">
                <H2>2. What we do not store</H2>
                <P>
                    We do not strip or keep the metadata inside your uploaded images — we re-encode images
                    on upload precisely so EXIF and GPS data from phone cameras is not stored. Videos keep
                    their original metadata until transcoding is added.
                </P>
            </section>

            <section className="space-y-3">
                <H2>3. Cookies</H2>
                <UL>
                    <li><strong className="text-ink-text">inkwell_age_ok</strong> — remembers that you confirmed you are 18 or older. One year.</li>
                    <li><strong className="text-ink-text">Session</strong> — keeps you logged in.</li>
                </UL>
                <P>
                    Advertising networks may set their own cookies when ads load. Those are controlled by
                    the network, not this site.
                </P>
            </section>

            <section className="space-y-3">
                <H2>4. Who can see what</H2>
                <P>
                    Your posts and profile are public unless marked otherwise. Explicit posts are hidden
                    from people who have not opted in to explicit content, and DMs are only visible to the
                    people in the conversation.
                </P>
                <P>Creator Studio analytics and planner entries are private to the account owner. Shared artwork statistics describe uploads; they do not establish ownership of the artwork.</P>
                <P>Collection folders are private unless you share them by link. Anyone with an active link can see the folder name and works permitted by their content settings. Making a folder private revokes that link; deleting a folder does not delete its original media.</P>
            </section>

            <section className="space-y-3">
                <H2>5. Sharing</H2>
                <P>
                    We share data with service providers needed to run the site (hosting, storage, email,
                    ad networks) and where the law requires it. We do not sell personal data.
                </P>
            </section>

            <section className="space-y-3">
                <H2>6. Deleting your data</H2>
                <P>
                    You can delete your posts and comments at any time. Deleted content is removed from
                    public view; we keep what is needed to honour moderation decisions and the law.
                    Closing your account removes your content from the site.
                </P>
            </section>

            <section className="space-y-3">
                <H2>7. Your rights</H2>
                <P>
                    Depending on where you live you may have the right to access, correct, export or
                    delete your data. Use the contact address in the site footer.
                </P>
            </section>
        </LegalPage>
    );
}
