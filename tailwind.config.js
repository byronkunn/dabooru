import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            colors: {
                ink: {
                    bg: '#0d0b0f',
                    surface: '#17141a',
                    raised: '#211d26',
                    line: '#2e2833',
                    text: '#efeaf2',
                    muted: '#a297ab',
                    faint: '#7b6f85',
                    brand: '#e8b04b',
                    pink: '#c9527a',
                    sfw: '#4ecb8a',
                    sug: '#e8b04b',
                    expl: '#e2556b',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                card: '14px',
                panel: '16px',
            },
            maxWidth: {
                shell: '1560px',
            },
        },
    },

    plugins: [forms],
};
