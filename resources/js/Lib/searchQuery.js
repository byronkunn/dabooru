export function searchFragment(query) {
    return query.match(/(?:^|\s)((?:-?tag:|by:|artist:|#)?(?:"[^"]*"?|[^\s]*))$/)?.[1] ?? '';
}

export function appendSearchTag(query, tag) {
    const token = `tag:${JSON.stringify(tag.replace(/^#/, ''))}`;
    if (query.includes(token)) return query;
    return `${query.trim()} ${token} `.trimStart();
}

export function selectSearchSuggestion(query, row) {
    const fragment = searchFragment(query);
    const prefix = query.slice(0, query.length - fragment.length).trimEnd();
    if (row.kind === 'tag') return appendSearchTag(prefix, row.tag || row.label);
    const token = row.kind === 'artist' ? `by:${JSON.stringify(row.hint.replace(/^@/, ''))}`
        : row.kind === 'external_artist' ? `by:${JSON.stringify(row.label)}`
            : JSON.stringify(row.label);
    return `${prefix} ${token} `.trimStart();
}
