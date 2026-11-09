import test from 'node:test';
import assert from 'node:assert/strict';
import { getSavedWorkRevision, rememberWorkEngagement } from '../../resources/js/Lib/workEngagement.js';

test('library refresh revisions change for saves and memberships, but not likes or other accounts', () => {
    const reader = 987654;
    const other = 987655;
    const original = getSavedWorkRevision(reader);
    rememberWorkEngagement(reader, 1, 'liked', true);
    assert.equal(getSavedWorkRevision(reader), original);
    rememberWorkEngagement(reader, 1, 'saved', true);
    assert.equal(getSavedWorkRevision(reader), original + 1);
    rememberWorkEngagement(reader, 1, 'saved', true);
    assert.equal(getSavedWorkRevision(reader), original + 2);
    rememberWorkEngagement(reader, 1, 'saved', false);
    assert.equal(getSavedWorkRevision(reader), original + 3);
    assert.equal(getSavedWorkRevision(other), 0);
});
