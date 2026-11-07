import { LegalPage, H2, P, UL } from '@/Components/LegalPage';

export default function ContentPolicy({ updated }) {
    return (
        <LegalPage
            title="Content policy"
            updated={updated}
            intro={
                <>
                    This is the rulebook for what can be posted here. It is enforced, and breaking it
                    gets your account removed. If you are unsure whether something is allowed, don&apos;t post it
                    and ask first.
                </>
            }
        >
            <section className="space-y-3">
                <H2>1. Adults only</H2>
                <P>
                    You must be 18 or older to have an account or view explicit work. Accounts that
                    appear to belong to a minor are removed and their content is deleted immediately.
                </P>
            </section>

            <section className="space-y-3">
                <H2>2. No minors, ever</H2>
                <P>
                    <strong className="text-ink-text">
                        Do not post sexual or sexualised depictions of minors.
                    </strong>{' '}
                    This applies to drawings, comics, animation, AI-generated images and any character
                    who reads as a child or adolescent — regardless of art style, stated age, or
                    fictional setting. This is the one rule with no exceptions and no debate. It is a
                    criminal matter in most jurisdictions, and we act on reports of it within hours.
                </P>
                <P>
                    Characters in adult content must be clearly adults. Characters explicitly written
                    as under 18 must never appear in sexual content, even &quot;aged up&quot;.
                </P>
            </section>

            <section className="space-y-3">
                <H2>3. No real people without consent</H2>
                <UL>
                    <li>No sexual content depicting a real person without their consent.</li>
                    <li>No deepfakes or face-swaps of real people.</li>
                    <li>No revenge porn, leaks, or private material.</li>
                </UL>
                <P>Non-consensual material is removed on sight and the account is banned.</P>
            </section>

            <section className="space-y-3">
                <H2>4. Your own work — and correctly credited work</H2>
                <P>
                    Post work you made. Reposting someone else&apos;s art without permission is not
                    allowed. If you post a collaboration or a commission, credit the other artist.
                    Copyright complaints are handled through the{' '}
                    <a href={route('dmca')} className="link-brand">
                        DMCA process
                    </a>
                    .
                </P>
            </section>

            <section className="space-y-3">
                <H2>5. Label your work</H2>
                <P>Every post carries a rating, and you choose it when you upload:</P>
                <UL>
                    <li>
                        <strong className="text-ink-text">SFW</strong> — safe to be seen in a workplace.
                    </li>
                    <li>
                        <strong className="text-ink-text">Suggestive</strong> — sexualised framing, nudity
                        implied or partial, but not explicit.
                    </li>
                    <li>
                        <strong className="text-ink-text">Explicit</strong> — nudity, sex acts, or anything
                        you would not want seen without warning.
                    </li>
                </UL>
                <P>
                    Explicit content is hidden from anyone who has not opted in, and is never shown to
                    a logged-out visitor. Mislabelling explicit work as safe is a reason for removal and,
                    if repeated, a ban.
                </P>
            </section>

            <section className="space-y-3">
                <H2>6. Not allowed</H2>
                <P>
                    Beyond the above: no content depicting animals in a sexual context, no incest
                    content, no gore or extreme violence beyond what can be shown in adult art generally,
                    no spam, scams, unsolicited DMs advertising anything, and nothing that targets or
                    harasses a person.
                </P>
            </section>

            <section className="space-y-3">
                <H2>7. How this is enforced</H2>
                <P>
                    Anyone can report a post, comment, profile or message. Reports go to a moderation
                    queue and are reviewed by a person. Depending on severity we may hide the content,
                    remove it, or ban the account. Every action we take is recorded.
                </P>
                <P>
                    If your content is removed you will usually lose it, not just its visibility, and
                    repeat offences end with the account closed. There is no appeal for anything in
                    section 2.
                </P>
            </section>
        </LegalPage>
    );
}
