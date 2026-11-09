/**
 * Official brand marks for the tip page.
 *
 * Inlined rather than pulled from an icon package: these are four fixed logos,
 * and a dependency for four paths is not worth the bundle or the supply chain.
 * Each is drawn on a 24×24 viewBox in its own single-colour form so it reads on
 * the coin's gradient tile.
 */
const MARKS = {
    // Monero "M" roundel
    monero: (
        <>
            <path d="M12 1a11 11 0 1 0 0 22 11 11 0 0 0 0-22zm0 2.2a8.8 8.8 0 1 1 0 17.6 8.8 8.8 0 0 1 0-17.6z" />
            <path d="M6.4 7v10h1.9v-5.7l3.7 3.8 3.7-3.8V17h1.9V7l-5.6 5.7L6.4 7z" />
        </>
    ),
    // Bitcoin "B" with the two strokes
    bitcoin: (
        <>
            <path d="M9.1 2.6v2.1h1.7V2.6h1.5v2.1c1.9.2 3.3 1.3 3.6 3 .2 1.2-.3 2.2-1.2 2.8 1.3.5 2.1 1.6 2.1 3.1 0 2.3-1.7 3.7-4.3 3.9v2.1h-1.7v-2.1h-1.7v2.1H7.6v-2.1H4.8v-1.9h1.5V8.5H4.8V6.6h2.8V2.6h1.5zm-.1 3.9v3.3h2.2c1.2 0 1.9-.6 1.9-1.7 0-1.1-.7-1.6-1.9-1.6H9zm0 5.1v3.7h2.6c1.4 0 2.2-.7 2.2-1.9 0-1.2-.8-1.8-2.2-1.8H9z" />
        </>
    ),
    // Ethereum diamond
    ethereum: (
        <>
            <path d="M12 1.5 5.6 12.1 12 15.9l6.4-3.8L12 1.5z" opacity="0.85" />
            <path d="M12 17.2 5.6 13.4 12 22.5l6.4-9.1-6.4 3.8z" opacity="0.6" />
        </>
    ),
    // Solana's three slanted bars
    solana: (
        <>
            <path d="M6.4 5.1a.9.9 0 0 1 .6-.25h14.2c.4 0 .58.47.31.74l-2.8 2.8a.9.9 0 0 1-.64.26H3.9c-.4 0-.58-.47-.31-.74l2.81-2.81z" />
            <path d="M18.3 9.6a.9.9 0 0 1-.64.26H3.48c-.4 0-.58.47-.31.74l2.8 2.8a.9.9 0 0 0 .64.26h14.16c.4 0 .58-.47.31-.74l-2.78-2.8z" />
            <path d="M6.4 14.9a.9.9 0 0 1 .6-.26h14.2c.4 0 .58.47.31.74l-2.8 2.8a.9.9 0 0 1-.64.26H3.9c-.4 0-.58-.47-.31-.74l2.81-2.8z" />
        </>
    ),
};

export function CryptoMark({ network, className = 'h-6 w-6' }) {
    const mark = MARKS[network];

    if (!mark) return null;

    return (
        <svg viewBox="0 0 24 24" className={className} fill="currentColor" aria-hidden="true" focusable="false">
            {mark}
        </svg>
    );
}

export function hasCryptoMark(network) {
    return Boolean(MARKS[network]);
}
