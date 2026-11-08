export const clearedGalleryFilters = {
    window: 'week', format: 'all', tag: '', q: '',
    min_duration: '', max_duration: '', tags: [], exclude_tags: [],
    min_likes: '', min_views: '',
};

export function patchGalleryFilters(filters, patch) {
    const next = { ...filters, ...patch };
    if (Object.hasOwn(patch, 'format') && patch.format !== 'video') {
        next.min_duration = '';
        next.max_duration = '';
    }
    return next;
}

export function formatDuration(seconds) {
    const value = Number(seconds);
    if (value < 60) return `${value}s`;
    const minutes = Math.floor(value / 60);
    const remainder = value % 60;
    return `${minutes}m${remainder ? ` ${remainder}s` : ''}`;
}

export function durationLabel(min, max) {
    if (!min && !max) return 'Any length';
    if (!max) return `${formatDuration(min)} or longer`;
    if (!min) return `Up to ${formatDuration(max)}`;
    return `${formatDuration(min)} – ${formatDuration(max)}`;
}

export function galleryFilterChips(filters, formatOptions, windowOptions) {
    const chips = [];
    if (filters.format !== 'all') chips.push({ key: 'format', label: formatOptions.find((o) => o.value === filters.format)?.label, patch: { format: 'all' } });
    if (filters.window !== 'week') chips.push({ key: 'window', label: windowOptions.find((o) => o.value === filters.window)?.label, patch: { window: 'week' } });
    if (filters.q) chips.push({ key: 'q', label: `“${filters.q}”`, patch: { q: '' } });
    if (filters.tag) chips.push({ key: 'tag', label: `#${filters.tag}`, patch: { tag: '' } });
    if (filters.min_duration || filters.max_duration) chips.push({ key: 'duration', label: durationLabel(filters.min_duration, filters.max_duration), patch: { min_duration: '', max_duration: '' } });
    if (filters.tags?.length) chips.push({ key: 'tags', label: `Any of: ${filters.tags.map((t) => `#${t}`).join(', ')}`, patch: { tags: [] } });
    if (filters.exclude_tags?.length) chips.push({ key: 'exclude_tags', label: `Exclude: ${filters.exclude_tags.map((t) => `#${t}`).join(', ')}`, patch: { exclude_tags: [] } });
    if (filters.min_likes) chips.push({ key: 'min_likes', label: `${filters.min_likes}+ likes`, patch: { min_likes: '' } });
    if (filters.min_views) chips.push({ key: 'min_views', label: `${filters.min_views}+ views`, patch: { min_views: '' } });
    return chips;
}

// Equal visual segments give short clips as much room as longer videos.
export const durationStops = [0, 15, 30, 60, 120, 300, 600, 1500, 86401];

export function durationPosition(seconds, unlimited = false) {
    if (seconds === '' || seconds == null) return unlimited ? 8 : 0;
    const value = Math.max(0, Math.min(86400, Number(seconds)));
    const index = durationStops.findIndex((stop) => stop >= value);
    if (index === 0) return 0;
    return index - 1 + (value - durationStops[index - 1]) / (durationStops[index] - durationStops[index - 1]);
}

export function durationAtPosition(position) {
    if (position >= 8) return '';
    const index = Math.floor(position);
    const value = Math.round(durationStops[index] + (durationStops[index + 1] - durationStops[index]) * (position - index));
    return position > 0 ? Math.max(1, value) : '';
}
