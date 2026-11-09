const changes = new Map();
const savedRevisions = new Map();

export function rememberWorkEngagement(userId, postId, kind, value) {
    const key = `${userId}:${postId}`;
    changes.set(key, { ...changes.get(key), [kind]: value });
    if (kind === 'saved') savedRevisions.set(userId, (savedRevisions.get(userId) ?? 0) + 1);
}

export function getSavedWorkRevision(userId) {
    return savedRevisions.get(userId) ?? 0;
}

export function getWorkEngagement(userId, postId) {
    return changes.get(`${userId}:${postId}`);
}

export function clearWorkEngagement(userId, postId, state) {
    const key = `${userId}:${postId}`;
    if (changes.get(key) === state) changes.delete(key);
}
