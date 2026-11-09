import test from 'node:test';
import assert from 'node:assert/strict';
import { categoryColor, categoryLabel } from '../../resources/js/Lib/tagCategories.js';

test('each category has a distinct production-safe palette and a readable label', () => {
    const categories = ['artist', 'character', 'series', 'medium', 'style', 'subject', 'anatomy',
        'clothing', 'relationships', 'themes', 'content warnings', 'general', 'position',
        'appearance', 'setting', 'composition'];
    assert.equal(new Set(categories.map(categoryColor)).size, categories.length);
    for (const category of categories) {
        assert.match(categoryColor(category), /^!border-\S+ !bg-\S+ !text-\S+$/);
        assert.ok(categoryLabel(category).length > 0);
    }
    assert.equal(categoryLabel('clothing'), 'Clothing / wearing');
    assert.equal(categoryLabel('position'), 'Pose / position');
});

test('missing categories use the neutral palette, not a guessed category', () => {
    assert.equal(categoryColor(undefined), categoryColor('general'));
    assert.equal(categoryColor('unknown'), categoryColor('general'));
});
