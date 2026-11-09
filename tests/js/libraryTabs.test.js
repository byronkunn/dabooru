import assert from 'node:assert/strict';
import test from 'node:test';

// The tab memory is plain localStorage, so it can be exercised without a DOM.
class MemoryStorage {
    #map = new Map();
    getItem(k) { return this.#map.has(k) ? this.#map.get(k) : null; }
    setItem(k, v) { this.#map.set(k, String(v)); }
    removeItem(k) { this.#map.delete(k); }
    clear() { this.#map.clear(); }
}

globalThis.window = { localStorage: new MemoryStorage() };

const LIBRARY_TABS = [['Collections', 'collections.index'], ['Saved Searches', 'saved-searches.index']];

function remembered(group, tabs, fallback) {
    const saved = window.localStorage.getItem(`inkwell.tab.${group}`);
    const known = tabs.some(([, destination]) => destination === saved);
    return known ? saved : fallback;
}

test('falls back to Collections before the reader has chosen', () => {
    window.localStorage.clear();
    assert.equal(remembered('library-sections', LIBRARY_TABS, 'collections.index'), 'collections.index');
});

test('remembers Saved Searches as the last Library section', () => {
    window.localStorage.setItem('inkwell.tab.library-sections', 'saved-searches.index');
    assert.equal(remembered('library-sections', LIBRARY_TABS, 'collections.index'), 'saved-searches.index');
});

test('a stale remembered tab that no longer exists falls back safely', () => {
    // e.g. New Chapters was removed from Library
    window.localStorage.setItem('inkwell.tab.library-sections', 'chapters.inbox');
    assert.equal(remembered('library-sections', LIBRARY_TABS, 'collections.index'), 'collections.index');
});

test('Library no longer lists New Chapters', () => {
    assert.equal(LIBRARY_TABS.some(([name]) => name === 'New Chapters'), false);
});

test('a disabled localStorage does not break navigation', () => {
    const broken = {
        getItem() { throw new Error('denied'); },
        setItem() { throw new Error('denied'); },
    };
    window.localStorage = broken;
    let href;
    try {
        const saved = window.localStorage.getItem('inkwell.tab.library-sections');
        href = saved ?? 'collections.index';
    } catch {
        href = 'collections.index';
    }
    assert.equal(href, 'collections.index');
    window.localStorage = new MemoryStorage();
});
