import { Link, useForm, usePage } from '@inertiajs/react';
import { Errors } from '@/Components/KnowledgeUi';

export default function TagFollowButton({ tag }) {
    const { auth } = usePage().props;
    const form = useForm({ tag });
    const following = (auth.followed_tags ?? []).some((item) => item.name === tag);
    if (!auth.user) return <Link href={route('login')} className="btn-ghost min-h-11">Log in to follow #{tag}</Link>;
    const toggle = () => {
        form.transform(() => ({ tag }));
        const options = { preserveScroll: true };
        if (following) form.delete(route('tags.unfollow'), options);
        else form.post(route('tags.follow'), options);
    };
    return (
        <div className="min-w-0">
            <button type="button" disabled={form.processing} onClick={toggle} className="btn-ghost min-h-11 max-w-full !whitespace-normal break-words" aria-label={`${following ? 'Unfollow' : 'Follow'} tag ${tag}`}>
                {form.processing ? 'Saving...' : `${following ? 'Unfollow' : 'Follow'} #${tag}`}
            </button>
            <Errors errors={form.errors} />
        </div>
    );
}
