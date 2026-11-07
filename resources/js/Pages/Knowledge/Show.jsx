import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout, { TopBar } from '@/Layouts/AppLayout';
import { WorkCell } from '@/Components/Work';
import { Errors, Pagination, ReportKnowledge, RevisionList, TagPicker } from '@/Components/KnowledgeUi';
import { useState } from 'react';

function Editor({ type, targetId, state, version, categories, relatedTags, trusted }) {
    const { auth } = usePage().props;
    const form = useForm({ ...state, summary: '', base_version: version });
    const [postTags, setPostTags] = useState((state.tags ?? []).map((name) => ({ name })));
    const [related, setRelated] = useState((state.related ?? []).map((id) => relatedTags.find((tag) => tag.id === id) ?? { id, name: `Tag ${id}` }));
    const [implies, setImplies] = useState((state.implies ?? []).map((id) => relatedTags.find((tag) => tag.id === id) ?? { id, name: `Tag ${id}` }));
    const [aliases, setAliases] = useState((state.aliases ?? []).join(', '));
    const [mergeDestination, setMergeDestination] = useState([]);
    const field = (key, label, options = {}) => (
        <label className="block">{label}
            <input className="field-input" value={form.data[key] ?? ''} onChange={(e) => form.setData(key, e.target.value)} {...options} />
        </label>
    );
    const rating = (key) => (
        <label className="block">Wiki visibility rating<select className="field-input" value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)}>
            <option value="sfw">SFW</option><option value="suggestive">Suggestive</option><option value="explicit">Explicit</option>
        </select></label>
    );
    return (
        <form className="space-y-3" onSubmit={(e) => {
            e.preventDefault();
            form.transform((data) => ({ ...data,
                ...(type === 'post' ? { tags: postTags.map((tag) => tag.name) } : {}),
                ...(type === 'tag' ? { aliases: aliases.split(',').map((name) => name.trim()).filter(Boolean),
                    related: related.map((tag) => tag.id), implies: implies.map((tag) => tag.id),
                    merged_into_id: data.merged_into_id || null, member_id: data.member_id || null } : {}),
            }));
form.post(route('knowledge.propose', { type, id: targetId }), { preserveScroll: true });
        }}>
            <p className="text-sm text-ink-muted">{trusted ? 'Ordinary edits publish immediately. Structural changes require moderator approval.' : 'Your edits are submitted for moderator approval.'} Include a clear reason and sources for factual claims. No private personal information.</p>
            {type === 'post' ? (
                <>
                    <p className="text-sm text-ink-muted">Only tags change here, not rating, creator credit, or ownership. Implied tags are added automatically.</p>
                    <TagPicker namesOnly label="Work tags" selected={postTags} onChange={setPostTags} />
                    <p className="text-sm">Added: {postTags.filter((tag) => !state.tags.includes(tag.name)).map((tag) => tag.name).join(', ') || 'none'}</p>
                    <p className="text-sm">Removed: {state.tags.filter((name) => !postTags.some((tag) => tag.name === name)).join(', ') || 'none'}</p>
                </>
            ) : (
                <>
                    {field(type === 'tag' ? 'name' : 'title', type === 'tag' ? 'Canonical name' : 'Title', { required: true, maxLength: type === 'tag' ? 40 : 160 })}
                    {type === 'tag' && (
                        <label className="block">Category<select className="field-input" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>
                            {categories.map((category) => <option key={category}>{category}</option>)}
                        </select></label>
                    )}
                    <label className="block">Definition / usage guidance (Markdown, no embedded images)
                        <textarea className="field-input min-h-64" maxLength={20000} value={form.data.wiki ?? form.data.body ?? ''} onChange={(e) => form.setData(type === 'tag' ? 'wiki' : 'body', e.target.value)} />
                    </label>
                    {rating(type === 'tag' ? 'wiki_rating' : 'rating')}
                    {type === 'tag' && (
                        <>
                            {field('source_url', 'Public source URL', { type: 'url', maxLength: 500 })}
                            <label className="block">Aliases (comma-separated)<input className="field-input" value={aliases} onChange={(e) => setAliases(e.target.value)} /></label>
                            <TagPicker label="Related tags (navigation only)" selected={related} onChange={setRelated} />
                            <TagPicker label="Implied tags (added automatically)" selected={implies} onChange={setImplies} />
                            <p className="text-sm text-ink-muted">Implication changes affect subsequent tag edits/uploads. Use a reviewed bulk proposal to update existing works.</p>
                            <TagPicker label="Merge destination (optional)" selected={mergeDestination} onChange={(selected) => {
                                const selection = selected.slice(-1);
                                setMergeDestination(selection);
                                form.setData('merged_into_id', selection[0]?.id ?? null);
                            }} />
                            <p className="text-sm text-ink-muted">Retag affected works and remove relationships before proposing a merge. Old names and links will redirect.</p>
                            {auth.user?.is_admin && form.data.category === 'artist' && (
                                <>
                                    <label className="block">Moderator-verified artist status<select className="field-input" value={form.data.artist_status} onChange={(e) => form.setData('artist_status', e.target.value)}>
                                        <option value="unverified">Unverified attribution</option><option value="external">External artist</option><option value="member">Verified site member</option>
                                    </select></label>
                                    {field('member_id', 'Verified member ID (only after checking identity)', { type: 'number', min: 1 })}
                                </>
                            )}
                        </>
                    )}
                </>
            )}
            {field('summary', 'Edit summary / reason', { required: true, minLength: 3, maxLength: 500 })}
            <Errors errors={form.errors} />
            <button disabled={form.processing} className="btn-primary">{trusted ? 'Submit edit' : 'Submit for review'}</button>
        </form>
    );
}

function BulkForm({ targetId, version, works }) {
    const form = useForm({ replacement_id: '', post_ids: [], summary: '', base_version: version });
    const [replacement, setReplacement] = useState([]);
    return (
        <details className="mt-6 rounded-xl border border-ink-line p-3">
            <summary className="cursor-pointer font-bold">Propose bulk retagging</summary>
            <form className="mt-3 space-y-3" onSubmit={(e) => { e.preventDefault(); form.post(route('knowledge.bulk', targetId), { preserveScroll: true }); }}>
                <p className="text-sm text-ink-muted">Replace this tag on selected visible works. Up to 100 per proposal. All bulk changes require review; the revision shows exact before/after tags.</p>
                <TagPicker label="Replacement canonical tag" selected={replacement} onChange={(selected) => {
                    const selection = selected.slice(-1);
                    setReplacement(selection);
                    form.setData('replacement_id', selection[0]?.id ?? '');
                }} />
                {works?.data.map((post) => (
                    <label key={post.id} className="flex gap-2"><input type="checkbox" checked={form.data.post_ids.includes(post.id)} onChange={(e) => form.setData('post_ids', e.target.checked ? [...form.data.post_ids, post.id] : form.data.post_ids.filter((id) => id !== post.id))} />#{post.id} {post.title ?? 'Untitled work'}</label>
                ))}
                <label className="block">Reason<input className="field-input" required minLength={3} maxLength={500} value={form.data.summary} onChange={(e) => form.setData('summary', e.target.value)} /></label>
                <Errors errors={form.errors} /><button disabled={form.processing || form.data.post_ids.length === 0 || !form.data.replacement_id} className="btn-primary">Create affected-work preview</button>
            </form>
        </details>
    );
}

export default function KnowledgeShow({ type, targetId, state, version, locked, html, categories, works, relatedTags, member, history, discussions, watching, canContribute, trusted, initialTab = 'wiki' }) {
    const { auth } = usePage().props;
    const [tab, setTab] = useState(initialTab);
    const discussion = useForm({ body: '' });
    const lockForm = useForm({ locked: !locked });
    const title = state.name ?? state.title ?? `Tags for work #${targetId}`;
    return (
        <AppLayout>
            <Head title={title} /><TopBar title={title} back={route('knowledge.index')} />
            <div className="mx-auto max-w-3xl space-y-5 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-2">
                    {type === 'tag' && <span className="chip">{state.category} · tag ID {targetId}</span>}
                    {locked && <span className="chip">Moderator locked</span>}
                    {auth.user && <button className="btn-ghost" onClick={() => router.post(route('knowledge.watch', { type, id: targetId }), { watching: !watching }, { preserveScroll: true })}>{watching ? 'Unwatch' : 'Watch changes'}</button>}
                    {auth.user?.staff_permissions?.includes('curator') && type !== 'post' && <button className="btn-ghost" disabled={lockForm.processing} onClick={() => { lockForm.transform(() => ({ locked: !locked })); lockForm.post(route('admin.knowledge.lock', { type, id: targetId }), { preserveScroll: true }); }}>{locked ? 'Unlock' : 'Lock page'}</button>}
                </div>
                <Errors errors={lockForm.errors} />
                {type === 'tag' && state.category === 'artist' && (
                    <div className="panel">
                        <b>{state.artist_status === 'member' && member ? 'Verified site member' : state.artist_status === 'external' ? 'External artist' : 'Unverified attribution'}</b>
                        {member && <Link href={route('profile.show', member.username)} className="ml-2 text-ink-brand">@{member.username}</Link>}
                        <p className="mt-2 text-sm text-ink-muted">This community page does not imply membership, endorsement, or permission to repost work.</p>
                    </div>
                )}
                <nav aria-label="Tag page sections" className="flex flex-wrap gap-2">
                    {['wiki', ...(works ? ['artwork'] : []), 'discussion', 'history', ...(canContribute ? ['edit'] : [])].map((key) => <button key={key} aria-current={tab === key ? 'page' : undefined} className={tab === key ? 'chip is-active' : 'chip'} onClick={() => setTab(key)}>{key}</button>)}
                </nav>
                {tab === 'wiki' && (
                    <section className="space-y-4">
                        {type === 'post' ? <><Link href={route('posts.show', targetId)} className="text-ink-brand">View artwork</Link><p>{state.tags.join(', ') || 'No tags yet.'}</p></> : html ? <div className="prose prose-invert max-w-none break-words [&_a]:text-ink-brand [&_p]:mb-3 [&_h2]:text-xl [&_ul]:list-disc [&_ul]:pl-6" dangerouslySetInnerHTML={{ __html: html }} /> : <p className="text-ink-muted">This page needs a definition and usage examples. Contributions are welcome.</p>}
                        {state.source_url && <a href={state.source_url} rel="nofollow noopener noreferrer" target="_blank" className="block text-ink-brand">Public source</a>}
                        {state.aliases?.length > 0 && <p>Aliases: {state.aliases.join(', ')}</p>}
                        {relatedTags.length > 0 && <div className="flex flex-wrap gap-2">{relatedTags.map((tag) => <Link key={tag.id} href={route('knowledge.show', { type: 'tag', id: tag.id })} className="chip">{tag.name}{state.implies.includes(tag.id) ? ' (implied)' : ''}</Link>)}</div>}
                    </section>
                )}
                {tab === 'artwork' && works && (
                    <section>
                        <p className="mb-3 text-sm text-ink-muted">{works.total} works visible under your content and blocking preferences.</p>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">{works.data.map((post) => <WorkCell key={post.id} post={post} />)}</div>
                        <Pagination data={works} />
                        {canContribute && !locked && <BulkForm targetId={targetId} version={version} works={works} />}
                    </section>
                )}
                {tab === 'history' && <RevisionList revisions={history} />}
                {tab === 'edit' && (locked && !auth.user?.is_admin ? <p>This page is locked. Report problems for moderator review.</p> : <Editor key={`${type}-${targetId}-${version}`} {...{ type, targetId, state, version, categories, relatedTags, trusted }} />)}
                {tab === 'discussion' && (
                    <section className="space-y-4">
                        {discussions.data.map((entry) => <div key={entry.id} className="rounded-xl border border-ink-line p-3"><p className="text-sm text-ink-muted">{entry.username ? `@${entry.username}` : 'Deleted contributor'}</p><p className="whitespace-pre-wrap break-words">{entry.body}</p>{canContribute && <ReportKnowledge type="discussion" id={entry.id} />}</div>)}
                        <Pagination data={discussions} />
                        {canContribute && (!locked || auth.user?.is_admin) && <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); discussion.post(route('knowledge.discuss', { type, id: targetId }), { preserveScroll: true, onSuccess: () => discussion.reset() }); }}>
                            <label className="block">Discuss an edit or definition<textarea className="field-input" required minLength={3} maxLength={3000} value={discussion.data.body} onChange={(e) => discussion.setData('body', e.target.value)} /></label>
                            <Errors errors={discussion.errors} /><button disabled={discussion.processing} className="btn-primary">Post discussion</button>
                        </form>}
                    </section>
                )}
                {!canContribute && <p className="text-sm text-ink-muted">A verified, unrestricted account is needed to contribute.</p>}
                {canContribute && <ReportKnowledge type={type} id={targetId} />}
            </div>
        </AppLayout>
    );
}
