export default function AiToolField({ form }) {
    return <fieldset className="space-y-2 rounded-xl border border-ink-line p-4">
        <legend className="px-1 text-sm font-semibold">AI tool used (optional)</legend>
        <p className="text-xs text-ink-muted">If AI helped create this work, you can share the tool. This appears on the artwork page.</p>
        <label className="block text-sm">Tool name<input className="field-input mt-1" value={form.data.ai_tool} onChange={(e) => form.setData('ai_tool', e.target.value)} maxLength={120} placeholder="For example, ChatGPT or Stable Diffusion" /></label>
        <label className="block text-sm">Tool category<select className="field-input mt-1" value={form.data.ai_tool_type} onChange={(e) => form.setData('ai_tool_type', e.target.value)}>
            <option value="">Choose a category</option><option value="local">Local tool</option><option value="service">Online service (free or paid)</option>
        </select></label>
        {(form.errors.ai_tool || form.errors.ai_tool_type) && <p className="field-hint text-ink-expl" role="alert">{form.errors.ai_tool || form.errors.ai_tool_type}</p>}
    </fieldset>;
}
