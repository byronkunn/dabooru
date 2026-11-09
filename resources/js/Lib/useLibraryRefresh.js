import { router, usePage, useRemember } from '@inertiajs/react';
import { useEffect } from 'react';
import { getSavedWorkRevision } from '@/Lib/workEngagement';

export default function useLibraryRefresh() {
    const { props, url } = usePage();
    const userId = props.auth.user?.id;
    const revision = getSavedWorkRevision(userId);
    const [seen, setSeen] = useRemember(revision, `library-revision:${userId}:${url}`);
    useEffect(() => {
        if (seen === revision) return;
        let second;
        const first = requestAnimationFrame(() => {
            second = requestAnimationFrame(() => {
                router.reload({ preserveScroll: true, preserveState: true, onSuccess: () => setSeen(revision) });
            });
        });
        return () => { cancelAnimationFrame(first); if (second) cancelAnimationFrame(second); };
    }, [seen, revision]);
}
