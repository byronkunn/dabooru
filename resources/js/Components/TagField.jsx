import { TagPicker } from '@/Components/KnowledgeUi';

export default function TagField({ form, maxTags = 30 }) {
    const tags = (form.data.tags ?? '').split(',').map((name) => name.trim()).filter(Boolean).map((name) => ({ name }));
    return (
        <div>
            <TagPicker namesOnly label="Tags" selected={tags} onChange={(selected) => form.setData('tags', selected.map((tag) => tag.name).join(', '))} />
            <p className={`field-hint ${tags.length > maxTags ? 'text-ink-expl' : ''}`} role={tags.length > maxTags ? 'alert' : undefined}>
                {tags.length} / {maxTags} tags{tags.length > maxTags ? ' — remove tags before saving.' : ''}
            </p>
            <p className={`field-hint ${form.errors.tags || form.errors.base_tag_version ? 'text-ink-expl' : ''}`} role={form.errors.tags || form.errors.base_tag_version ? 'alert' : undefined}>
                {form.errors.tags ?? form.errors.base_tag_version ?? `Add up to ${maxTags} tags of 40 characters. Canonical aliases and approved implications are applied automatically.`}
            </p>
        </div>
    );
}
