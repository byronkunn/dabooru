import { useForm } from '@inertiajs/react';
import { Errors } from '@/Components/KnowledgeUi';

export default function UserFollowButton({ username, following }) {
    const form = useForm({});
    const toggle = () => {
        const options = { preserveScroll: true };
        if (following) form.delete(route('users.unfollow', username), options);
        else form.post(route('users.follow', username), options);
    };
    return (
        <div className="min-w-0">
            <button type="button" disabled={form.processing} onClick={toggle} aria-label={`${following ? 'Unfollow' : 'Follow'} @${username}`} className={`${following ? 'btn-ghost' : 'btn-primary'} min-h-11`}>
                {form.processing ? 'Saving...' : following ? 'Unfollow' : 'Follow'}
            </button>
            <Errors errors={form.errors} />
        </div>
    );
}
