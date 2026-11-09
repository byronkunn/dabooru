export function compact(n) {
    const num = Number(n ?? 0);
    if (num < 1000) return String(num);
    if (num < 1_000_000) return `${(num / 1000).toFixed(num < 10_000 ? 1 : 0).replace(/\.0$/, '')}k`;
    return `${(num / 1_000_000).toFixed(1).replace(/\.0$/, '')}m`;
}

export function workArtistName(post, fallback = post.user?.name || 'Unsorted') {
    return post.credited_artist?.name || post.artist_name || post.external_artist?.name || fallback;
}

export const RATING_CLASS = {
    sfw: 'rate-badge-sfw',
    suggestive: 'rate-badge-suggestive',
    explicit: 'rate-badge-explicit',
};

export const KIND_ICON = {
    image: '▣',
    gif: '▤',
    video: '▶',
    album: '▦',
    comic: '▥',
};

export const KIND_LABEL = {
    image: 'Image',
    gif: 'GIF',
    video: 'Video',
    album: 'Album',
    comic: 'Comic',
};
