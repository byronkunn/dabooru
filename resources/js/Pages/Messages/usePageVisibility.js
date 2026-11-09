import { useEffect, useState } from 'react';

export default function usePageVisibility() {
    const [visible, setVisible] = useState(() => typeof document !== 'undefined' && !document.hidden);

    useEffect(() => {
        const update = () => setVisible(!document.hidden);
        document.addEventListener('visibilitychange', update);
        update();
        return () => document.removeEventListener('visibilitychange', update);
    }, []);

    return visible;
}
