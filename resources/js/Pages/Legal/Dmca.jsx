import { LegalPage, H2, P, UL } from '@/Components/LegalPage';
import { useForm, Link } from '@inertiajs/react';

export default function Dmca({ submitted }) {
    const form = useForm({
        reporter_name: '',
        reporter_email: '',
        rights_holder: '',
        url: '',
        description: '',
        good_faith: false,
        penalty_ack: false,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('dmca.submit'));
    };

    return (
        <LegalPage
            title="Copyright / DMCA"
            updated="2026-11-06"
            intro={
                <>
                    If someone has posted your work without permission, send a takedown notice below.
                    Only the copyright owner or their authorised agent can file one.
                </>
            }
        >
            <section className="space-y-3">
                <H2>Before you file</H2>
                <UL>
                    <li>Try contacting the poster first — most reposts are fixed quickly.</li>
                    <li>Make sure you actually own the rights. Crediting the artist does not give a third party the right to repost.</li>
                    <li>False notices carry legal liability. Do not report work you do not own.</li>
                </UL>
            </section>

            <section className="space-y-4">
                <H2>File a notice</H2>
                <form onSubmit={submit} className="space-y-3.5">
                    <div className="grid gap-3.5 sm:grid-cols-2">
                        <div>
                            <label htmlFor="reporter_name" className="field-label">Your name</label>
                            <input id="reporter_name" value={form.data.reporter_name} onChange={(e) => form.setData('reporter_name', e.target.value)} className="field-input" required />
                            {form.errors.reporter_name && <p className="field-hint text-ink-expl">{form.errors.reporter_name}</p>}
                        </div>
                        <div>
                            <label htmlFor="reporter_email" className="field-label">Email</label>
                            <input id="reporter_email" type="email" value={form.data.reporter_email} onChange={(e) => form.setData('reporter_email', e.target.value)} className="field-input" required />
                            {form.errors.reporter_email && <p className="field-hint text-ink-expl">{form.errors.reporter_email}</p>}
                        </div>
                    </div>
                    <div>
                        <label htmlFor="rights_holder" className="field-label">Rights holder (optional)</label>
                        <input id="rights_holder" value={form.data.rights_holder} onChange={(e) => form.setData('rights_holder', e.target.value)} className="field-input" placeholder="If you are filing for someone else" />
                    </div>
                    <div>
                        <label htmlFor="url" className="field-label">URL of the infringing content</label>
                        <input id="url" type="url" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} className="field-input" placeholder="https://…" required />
                        {form.errors.url && <p className="field-hint text-ink-expl">{form.errors.url}</p>}
                    </div>
                    <div>
                        <label htmlFor="description" className="field-label">Describe the work and the infringement</label>
                        <textarea id="description" rows={5} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className="field-input resize-none" required />
                        <p className="field-hint">{form.data.description.length} characters</p>
                        {form.errors.description && <p className="field-hint text-ink-expl">{form.errors.description}</p>}
                    </div>

                    <label className="flex items-start gap-2.5 text-[13px] leading-snug text-ink-muted">
                        <input type="checkbox" checked={form.data.good_faith} onChange={(e) => form.setData('good_faith', e.target.checked)} className="mt-0.5 h-5 w-5 shrink-0 rounded border-ink-line bg-ink-raised text-ink-brand focus:ring-ink-brand" />
                        <span>I have a good-faith belief that the use of this material is not authorised by the copyright owner, its agent, or the law.</span>
                    </label>
                    {form.errors.good_faith && <p className="field-hint text-ink-expl">{form.errors.good_faith}</p>}

                    <label className="flex items-start gap-2.5 text-[13px] leading-snug text-ink-muted">
                        <input type="checkbox" checked={form.data.penalty_ack} onChange={(e) => form.setData('penalty_ack', e.target.checked)} className="mt-0.5 h-5 w-5 shrink-0 rounded border-ink-line bg-ink-raised text-ink-brand focus:ring-ink-brand" />
                        <span>The information in this notice is accurate, and I acknowledge that a false statement may make me liable for damages, including costs and legal fees.</span>
                    </label>
                    {form.errors.penalty_ack && <p className="field-hint text-ink-expl">{form.errors.penalty_ack}</p>}

                    <button type="submit" disabled={form.processing} className="btn-primary w-full min-h-[48px]">
                        {form.processing ? 'Sending…' : 'Submit notice'}
                    </button>
                </form>
            </section>

            <section className="space-y-3">
                <H2>What happens next</H2>
                <P>
                    Every notice is stored with a timestamp and reviewed by a moderator. If the notice is
                    valid the content is removed and the poster is told. We keep a permanent record of
                    every notice and what we did about it.
                </P>
                <P>
                    <Link href={route('legal.policy')} className="link-brand">
                        Read the full content policy
                    </Link>
                </P>
            </section>
        </LegalPage>
    );
}
