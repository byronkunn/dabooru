import assert from 'node:assert/strict';
import test from 'node:test';
import {
    clearedGalleryFilters, durationAtPosition, durationLabel, durationPosition,
    galleryFilterChips, patchGalleryFilters,
} from '../../resources/js/Lib/galleryFilters.js';

const filters = { ...clearedGalleryFilters, sort: 'views', explicit: true, format: 'video', min_duration: 15, max_duration: 1500 };

test('sort changes retain every filter and content preference', () => {
    assert.deepEqual(patchGalleryFilters(filters, { sort: 'newest' }), { ...filters, sort: 'newest' });
});

test('clear filters preserves sort and content preference', () => {
    const cleared = patchGalleryFilters(filters, clearedGalleryFilters);
    assert.equal(cleared.sort, 'views');
    assert.equal(cleared.explicit, true);
    assert.equal(galleryFilterChips(cleared, [], []).length, 0);
});

test('badge counts duration once and never counts sort or explicit preference', () => {
    assert.equal(galleryFilterChips(filters, [{ value: 'video', label: 'Videos' }], []).length, 2);
});

test('non-video format clears duration while preserving other filters', () => {
    for (const format of ['all', 'image', 'gif', 'album', 'comic']) {
        assert.deepEqual(patchGalleryFilters(filters, { format }), { ...filters, format, min_duration: '', max_duration: '' });
    }
});

test('tag changes do not reset time period or sort', () => {
    assert.deepEqual(patchGalleryFilters({ ...filters, window: 'month' }, { tag: 'study' }), { ...filters, window: 'month', tag: 'study' });
});

test('duration ticks and precise long values round-trip without a 25-minute cap', () => {
    for (const seconds of [0, 15, 30, 60, 120, 300, 600, 1500, 1800, 3600, 5400, 86400]) {
        assert.equal(durationAtPosition(durationPosition(seconds)), seconds || '');
    }
    assert.equal(durationPosition('', true), 8);
    assert.equal(durationAtPosition(8), '');
    assert.equal(durationLabel(1500, ''), '25m or longer');
    assert.equal(durationLabel('', ''), 'Any length');
    assert.equal(durationLabel(15, 90), '15s – 1m 30s');
});
