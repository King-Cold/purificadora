import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Poppins', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    dark: '#0f172a',
                    navy: '#0b192c',
                    footer: '#0e1c2e',
                    primary: '#0084ff',
                    sky: '#14b8a6',
                    cyan: '#00b4d8',
                    teal: '#00d084',
                    accent: '#06d6a0',
                    lightBg: '#f8fafc',
                    grayText: '#64748b',
                },
            },
        },
    },

    plugins: [forms],
};
