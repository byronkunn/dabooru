import { useEffect, useRef, useState } from 'react';

function VideoThumbnail({ url, className }) {
    const ref = useRef(null);
    const [visible, setVisible] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                setVisible(true);
                observer.disconnect();
            }
        }, { rootMargin: '200px' });
        observer.observe(ref.current);
        return () => observer.disconnect();
    }, []);

    if (failed) {
        return (
            <span className={`grid place-items-center bg-ink-raised p-3 text-center text-xs text-ink-muted ${className}`}>
                Video preview unavailable in this browser
            </span>
        );
    }

    return (
        <video
            ref={ref}
            src={visible ? url : undefined}
            muted
            playsInline
            preload="metadata"
            aria-hidden="true"
            tabIndex={-1}
            className={className}
            onError={() => setFailed(true)}
        />
    );
}

export default function MediaThumbnail({ media, className = '' }) {
    if (media.is_video && !media.thumb) {
        return <VideoThumbnail key={media.url} url={media.url} className={className} />;
    }

    return (
        <img
            src={media.thumb || media.url}
            alt=""
            loading="lazy"
            decoding="async"
            className={className}
        />
    );
}
