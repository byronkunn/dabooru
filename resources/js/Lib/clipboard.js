export async function copyText(text) {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        // Fall back for older browsers and contexts that restrict clipboard access.
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    field.setSelectionRange(0, field.value.length);
    let copied = false;
    try {
        copied = typeof document.execCommand === 'function' && document.execCommand('copy');
    } catch {
        copied = false;
    } finally {
        document.body.removeChild(field);
    }

    return copied;
}
