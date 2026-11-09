import { LegalPage, H2, P, UL } from '@/Components/LegalPage';

export default function Terms({ updated }) {
    return (
        <LegalPage
            title="Terms of service"
            updated={updated}
            intro={
                <>
                    These terms are a starting point written for this site — have a lawyer review them
                    before you open the site to the public. They use plain language on purpose; the
                    numbered points are what actually bind a user.
                </>
            }
        >
            <section className="space-y-3">
                <H2>1. What this is</H2>
                <P>
                    Dabooru is a gallery and social site for artists, including adult work. By creating an
                    account or using the site you agree to these terms, to the content policy and to the
                    privacy policy.
                </P>
            </section>

            <section className="space-y-3">
                <H2>2. Who can use it</H2>
                <UL>
                    <li>You must be 18 or older. Registering if you are younger is a breach of these terms.</li>
                    <li>You must be legally allowed to view adult material where you live.</li>
                    <li>You are responsible for keeping your account credentials safe.</li>
                </UL>
            </section>

            <section className="space-y-3">
                <H2>3. Your work stays yours</H2>
                <P>
                    You keep ownership of everything you post. You grant us a non-exclusive, worldwide,
                    royalty-free licence to host, display, resize and serve your content for the purpose of
                    running the site. That licence ends when you delete the content, except for copies kept
                    in backups and records.
                </P>
                <P>
                    You confirm you own or have permission to post everything you upload, and that you have
                    the consent of any real person depicted.
                </P>
            </section>

            <section className="space-y-3">
                <H2>4. Content and moderation</H2>
                <P>
                    You are responsible for your posts. We can remove content or close an account that
                    breaks the content policy, the law, or an ad network&apos;s rules — at our discretion and
                    without notice for serious breaches. Section 2 of the content policy has no appeal.
                </P>
            </section>

            <section className="space-y-3">
                <H2>5. Ads</H2>
                <P>
                    The site is free and funded by advertising. Ads are served by third-party networks which
                    may set their own cookies; see the privacy policy.
                </P>
            </section>

            <section className="space-y-3">
                <H2>6. No payment now</H2>
                <P>
                    Nothing on the site costs money and artists are not paid by us. If paid features are
                    added later, their own terms will apply and will be shown before you buy anything.
                </P>
            </section>

            <section className="space-y-3">
                <H2>7. Ending your account</H2>
                <P>
                    You can stop using the site at any time. We can suspend or close an account that breaks
                    these terms. If your account is closed for breaking the rules, you will not be refunded
                    anything (there is nothing paid).
                </P>
            </section>

            <section className="space-y-3">
                <H2>8. Disclaimers</H2>
                <P>
                    The site is provided &quot;as is&quot;. Opinions in content are the artist&apos;s, not ours.
                    We do not guarantee the site will be available, secure, or error-free.
                </P>
            </section>

            <section className="space-y-3">
                <H2>9. Changes</H2>
                <P>
                    If these terms change in a way that matters, we will say so on the site before the
                    change takes effect. Continuing to use the site after that means you accept the
                    new terms.
                </P>
            </section>
        </LegalPage>
    );
}
