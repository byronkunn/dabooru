const base = {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 2,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
};

export function Icon({ name, className = 'w-5 h-5' }) {
    const paths = {
        gallery: (
            <>
                <rect x="3" y="3" width="7" height="7" rx="1.5" />
                <rect x="14" y="3" width="7" height="7" rx="1.5" />
                <rect x="3" y="14" width="7" height="7" rx="1.5" />
                <rect x="14" y="14" width="7" height="7" rx="1.5" />
            </>
        ),
        following: <path d="M4 6h16M4 12h16M4 18h10" />,
        chat: <path d="M4 4h16v12H5.2L4 17.5V4z" />,
        bell: (
            <>
                <path d="M12 4a6 6 0 0 0-6 6v3l-1.5 4h15L18 13v-3a6 6 0 0 0-6-6z" />
                <path d="M9.5 20a2.5 2.5 0 0 0 5 0" />
            </>
        ),
        bookmark: <path d="M4 5h10a4 4 0 0 1 0 8H6l-2 3V5z" />,
        user: (
            <>
                <circle cx="12" cy="8" r="3.5" />
                <path d="M4.5 20a7.5 7.5 0 0 1 15 0" />
            </>
        ),
        settings: (
            <>
                <circle cx="12" cy="12" r="3" />
                <path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1" />
            </>
        ),
        heart: <path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z" />,
        comment: <path d="M4 5h16v11H5.5L4 18.5V5z" />,
        save: <path d="M6 4h12v17l-6-4-6 4V4z" />,
        share: (
            <>
                <path d="M4 12v7h16v-7" />
                <path d="M12 15V4M8 8l4-4 4 4" />
            </>
        ),
        eye: (
            <>
                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z" />
                <circle cx="12" cy="12" r="2.5" />
            </>
        ),
        search: (
            <>
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-3.5-3.5" />
            </>
        ),
        back: <path d="M15 5l-7 7 7 7" />,
        close: <path d="M6 6l12 12M18 6L6 18" />,
        plus: <path d="M12 5v14M5 12h14" />,
        send: <path d="M4 12l16-8-6 16-3-6-7-2z" />,
        more: (
            <>
                <circle cx="5" cy="12" r="1.5" fill="currentColor" stroke="none" />
                <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" />
                <circle cx="19" cy="12" r="1.5" fill="currentColor" stroke="none" />
            </>
        ),
        analytics: <path d="M4 20h16M6 16v-4m6 4V5m6 11V9" />,
        flag: <path d="M5 21V4h9l-1 3h6l-2 5 2 5h-7l-1-3H5" />,
        play: <path d="M8 5v14l11-7z" fill="currentColor" stroke="none" />,
        check: <path d="M5 13l4 4L19 7" />,
        upload: (
            <>
                <path d="M12 16V4M8 8l4-4 4 4" />
                <path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" />
            </>
        ),
        fullscreen: (
            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3" />
        ),
    };

    return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true" {...base}>
            {paths[name] ?? paths.gallery}
        </svg>
    );
}
