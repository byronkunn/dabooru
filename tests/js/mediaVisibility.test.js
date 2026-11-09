import test from 'node:test';
import assert from 'node:assert/strict';
import { shouldBlurMedia } from '../../resources/js/Lib/mediaVisibility.js';

test('show explicit preference reveals NSFW without a separate click', () => {
    assert.equal(shouldBlurMedia('explicit', true), false);
    assert.equal(shouldBlurMedia('explicit', false), true);
    assert.equal(shouldBlurMedia('explicit', undefined), true);
    assert.equal(shouldBlurMedia('explicit', false, true), false);
    assert.equal(shouldBlurMedia('sfw', false), false);
    assert.equal(shouldBlurMedia('suggestive', false), false);
});
