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
                    blue: '#0E1E42',       // PANTONE 2767C Glory Blue
                    'blue-hover': '#162C5B',
                    'blue-light': '#F0F4FA',
                    red: '#AF143C',        // PANTONE 202C Glory Red
                    'red-hover': '#8F1031',
                    'red-light': '#FDF2F4',
                    white: '#FFFFFF',      // PANTONE 663C Pure White
                    cream: '#EFE7DC',      // Warm Cream / Sand
                    'cream-light': '#FAF7F2',
                    silver: '#BABABA',     // Neutral Silver
                    dark: '#0E1E42',
                    navy: '#0E1E42',
                    footer: '#0E1E42',
                    primary: '#0E1E42',
                    accent: '#AF143C',
                },
            },
        },
    },

    plugins: [forms],
};
