import test from 'node:test';
import assert from 'node:assert/strict';
import { appendSearchTag, searchFragment, selectSearchSuggestion } from '../../resources/js/Lib/searchQuery.js';

test('tag chips append multiple quoted tags without losing free text or duplicating chips', () => {
    const query = appendSearchTag(appendSearchTag('mountain', 'line art'), 'landscape');
    assert.equal(query, 'mountain tag:"line art" tag:"landscape" ');
    assert.equal(appendSearchTag(query, 'line art'), query);
});

test('autocomplete only replaces the unfinished final token', () => {
    const query = 'mountain tag:"line art" #land';
    assert.equal(searchFragment(query), '#land');
    assert.equal(selectSearchSuggestion(query, { kind: 'tag', tag: 'landscape' }), 'mountain tag:"line art" tag:"landscape" ');
    assert.equal(searchFragment('tag:"line art" '), '');
    assert.equal(selectSearchSuggestion('tag:"line art" rin', { kind: 'artist', hint: '@rin_senpai' }), 'tag:"line art" by:"rin_senpai" ');
    assert.equal(selectSearchSuggestion('mount', { kind: 'work', label: 'Mountain quiet' }), '"Mountain quiet" ');
});
