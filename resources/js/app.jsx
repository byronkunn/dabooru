import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'ModernBooru';

/**
 * Carry over preferences saved under the old brand's storage keys.
 * Runs once: the old keys are removed after copying.
 */
(function migrateLocalStorage() {
    const moved = [
        ['inkwell.readerMode', 'modernbooru.readerMode'],
        ['inkwell.readerInterval', 'modernbooru.readerInterval'],
    ];
    try {
        for (const [from, to] of moved) {
            const value = window.localStorage.getItem(from);
            if (value !== null && window.localStorage.getItem(to) === null) {
                window.localStorage.setItem(to, value);
            }
            window.localStorage.removeItem(from);
        }
        // tab memory uses a dynamic suffix, so sweep every matching key
        for (let i = window.localStorage.length - 1; i >= 0; i--) {
            const key = window.localStorage.key(i);
            if (key && key.startsWith('inkwell.tab.')) {
                const next = key.replace('inkwell.tab.', 'modernbooru.tab.');
                const value = window.localStorage.getItem(key);
                if (value !== null && window.localStorage.getItem(next) === null) {
                    window.localStorage.setItem(next, value);
                }
                window.localStorage.removeItem(key);
            }
        }
    } catch {
        /* storage blocked — nothing to migrate */
    }
})();

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#e8b04b',
    },
});
