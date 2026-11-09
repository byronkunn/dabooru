import { Link, router } from '@inertiajs/react';
import { useRef } from 'react';

const WORK_RETURN = 'work-return';

export function returnToWorkSource(event) {
    const entry = router.restore(WORK_RETURN);
    if (!entry || entry.work !== window.location.pathname || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    event.preventDefault();
    const stop = router.on('navigate', ({ detail: { page } }) => {
        stop();
        const url = new URL(page.url, window.location.origin);
        if (url.pathname + url.search !== entry.source) return;

        let frames = 0;
        const restore = () => {
            window.scrollTo(0, entry.scroll);
            if (Math.abs(window.scrollY - entry.scroll) > 1 && ++frames < 12) requestAnimationFrame(restore);
        };
        requestAnimationFrame(() => requestAnimationFrame(restore));
    });
    window.history.back();
}

export default function WorkLink({ href, ...props }) {
    const source = useRef(null);

    return <Link {...props} href={href} onClick={() => {
        source.current = { source: window.location.pathname + window.location.search, scroll: window.scrollY };
    }} onSuccess={() => {
        if (source.current && source.current.source !== window.location.pathname + window.location.search) {
            router.remember({ ...source.current, work: window.location.pathname }, WORK_RETURN);
        }
    }} />;
}
