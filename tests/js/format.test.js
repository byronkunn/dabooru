import test from 'node:test';
import assert from 'node:assert/strict';
import { workArtistName } from '../../resources/js/Lib/format.js';

test('work artist name prioritizes credit over uploader', () => {
    const work = {
        credited_artist: { name: 'Verified artist' },
        artist_name: 'Original credit',
        external_artist: { name: 'External artist' },
        user: { name: 'Uploader' },
    };
    assert.equal(workArtistName(work), 'Verified artist');
    assert.equal(workArtistName({ ...work, credited_artist: null }), 'Original credit');
    assert.equal(workArtistName({ ...work, credited_artist: null, artist_name: null }), 'External artist');
    assert.equal(workArtistName({ user: work.user }), 'Uploader');
    assert.equal(workArtistName({}), 'Unsorted');
    assert.equal(workArtistName({ user: work.user }, 'Unknown artist'), 'Unknown artist');
});
