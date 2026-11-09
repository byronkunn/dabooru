import { Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AdminErrors } from '@/Components/AdminUi';
import ReportDialog from '@/Components/ReportDialog';

function CommentItem({ comment, postId, canComment, root = comment }) {
    const [replying, setReplying] = useState(false);
    const [editing, setEditing] = useState(false);
    const [reporting, setReporting] = useState(false);
    const { auth } = usePage().props;
    const reply = useForm({ body: '', parent_id: root.id });
    const edit = useForm({ body: comment.body ?? '' });
    const remove = useForm({});
    const canReply = canComment && root.canReply && comment.canReply;

    return (
        <div id={`comment-${comment.id}`} className="grid grid-cols-[36px_minmax(0,1fr)] gap-2.5">
            {comment.user ? <Link href={route('profile.show', comment.user.username)}>
                <img src={comment.user.avatar} alt="" className="h-9 w-9 rounded-full object-cover" />
            </Link> : <span className="grid h-9 w-9 place-items-center rounded-full bg-ink-raised text-xs text-ink-muted">—</span>}
            <div className="min-w-0">
                <p className="flex flex-wrap items-center gap-1.5 text-sm">
                    {comment.user ? <Link href={route('profile.show', comment.user.username)} className="font-bold hover:underline">{comment.user.name}</Link>
                        : <span className="text-ink-muted">{comment.deleted ? 'Deleted comment' : 'Deleted account'}</span>}
                    <span className="text-xs text-ink-muted">{comment.created_at_human}{comment.edited ? ' · edited' : ''}</span>
                </p>
                {editing ? <form className="mt-2 space-y-2" onSubmit={(event) => {
                    event.preventDefault();
                    edit.patch(route('comments.update', comment.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
                }}>
                    <textarea aria-label="Edit comment" className="field-input w-full" rows={3} maxLength={2000} required autoFocus
                        value={edit.data.body} onChange={(event) => edit.setData('body', event.target.value)} />
                    <AdminErrors errors={edit.errors} />
                    <div className="flex flex-wrap gap-2">
                        <button className="btn-primary" disabled={edit.processing || !edit.data.body.trim()}>Save comment</button>
                        <button type="button" className="btn-ghost" disabled={edit.processing} onClick={() => { setEditing(false); edit.clearErrors(); }}>Cancel</button>
                    </div>
                </form> : <p className="mt-1 whitespace-pre-wrap break-words text-[14.5px] leading-snug">{comment.deleted ? 'This comment was deleted. Replies are preserved below.' : comment.body}</p>}
                <div className="mt-2 flex flex-wrap gap-3 text-xs font-semibold text-ink-muted">
                    {canReply && <button type="button" className="min-h-9 hover:text-ink-brand" onClick={() => {
                        setReplying(!replying);
                        if (!replying && !reply.data.body && comment.id !== root.id && comment.user) reply.setData('body', `@${comment.user.username} `);
                    }}>Reply</button>}
                    {comment.canEdit && !editing && <button type="button" className="min-h-9 hover:text-ink-brand" onClick={() => { edit.setData('body', comment.body); setEditing(true); }}>Edit</button>}
                    {comment.canDelete && <button type="button" className="min-h-9 hover:text-ink-expl" disabled={remove.processing} onClick={() => {
                        if (window.confirm('Delete this comment? Existing replies will be kept.')) remove.delete(route('comments.destroy', comment.id), { preserveScroll: true });
                    }}>{remove.processing ? 'Deleting…' : 'Delete'}</button>}
                    {auth.user && !comment.deleted && !comment.canDelete && <button type="button" className="min-h-9 hover:text-ink-expl" onClick={() => setReporting(true)}>Report</button>}
                </div>
                <AdminErrors errors={remove.errors} />
                {reporting && <ReportDialog type="comment" id={comment.id} onClose={() => setReporting(false)} />}
                {replying && <form className="mt-2 space-y-2" onSubmit={(event) => {
                    event.preventDefault();
                    reply.post(route('comments.store', postId), { preserveScroll: true, onSuccess: () => { reply.reset(); setReplying(false); } });
                }}>
                    <label className="field-label" htmlFor={`reply-${comment.id}`}>Reply to {comment.user?.name || 'this thread'}</label>
                    <textarea id={`reply-${comment.id}`} rows={2} maxLength={2000} required autoFocus className="field-input w-full"
                        value={reply.data.body} onChange={(event) => reply.setData('body', event.target.value)} />
                    <AdminErrors errors={reply.errors} />
                    <div className="flex flex-wrap gap-2">
                        <button className="btn-primary" disabled={reply.processing || !reply.data.body.trim()}>Post reply</button>
                        <button type="button" className="btn-ghost" disabled={reply.processing} onClick={() => setReplying(false)}>Cancel reply</button>
                    </div>
                </form>}
                {comment.replies?.length > 0 && <div aria-label="Replies" className="mt-3 space-y-4 border-l border-ink-line pl-2">
                    {comment.replies.map((child) => <CommentItem key={child.id} comment={child} root={root} postId={postId} canComment={canComment} />)}
                </div>}
            </div>
        </div>
    );
}

export default function PostComments({ post, comments, canComment, pagination = {} }) {
    const { auth } = usePage().props;
    const form = useForm({ body: '' });
    return (
        <section id="comments" className="mx-auto max-w-[720px]" aria-label="Comments">
            <h2 className="px-4 pt-4 font-bold">Comments ({post.comments})</h2>
            {canComment ? <form className="space-y-2 border-b border-ink-line px-4 py-3.5 md:px-5" onSubmit={(event) => {
                event.preventDefault();
                form.post(route('comments.store', post.id), { preserveScroll: true, onSuccess: () => form.reset() });
            }}>
                <label htmlFor="new-comment" className="field-label">Add a comment</label>
                <textarea id="new-comment" className="field-input w-full" rows={3} maxLength={2000} required
                    value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} placeholder="Share your thoughts…" />
                <AdminErrors errors={form.errors} />
                <button className="btn-primary" disabled={form.processing || !form.data.body.trim()}>{form.processing ? 'Posting…' : 'Post comment'}</button>
            </form> : <div className="border-b border-ink-line px-4 py-4 text-sm text-ink-muted">
                {auth.user ? 'Commenting is unavailable for this work.' : <>Log in to comment or reply. <Link href={route('login')} className="link-brand">Log in</Link></>}
            </div>}
            {comments.length === 0 ? <p className="px-5 py-10 text-center text-sm text-ink-muted">No comments yet.</p>
                : comments.map((comment) => <div key={comment.id} className="border-b border-ink-line px-4 py-3.5 md:px-5">
                    <CommentItem comment={comment} postId={post.id} canComment={canComment} />
                </div>)}
            {(pagination.nextPage || pagination.previousPage) && <nav aria-label="Comment pages" className="flex flex-wrap gap-2 p-4">
                {pagination.previousPage && <Link href={pagination.previousPage} className="btn-ghost">Newer comments</Link>}
                {pagination.nextPage && <Link href={pagination.nextPage} className="btn-ghost">Older comments</Link>}
            </nav>}
        </section>
    );
}
