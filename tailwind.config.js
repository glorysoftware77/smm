import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                glory: {
                    50: '#f8f1f3',
                    100: '#f0e2e6',
                    200: '#e0c5cd',
                    300: '#c896a3',
                    400: '#8a4456',
                    500: '#6B2C3E',
                    600: '#572433',
                    700: '#461d29',
                    800: '#351620',
                    900: '#261118',
                    950: '#16090d',
                },
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                soft: '0 1px 2px rgba(26, 29, 35, 0.04), 0 16px 40px -24px rgba(26, 29, 35, 0.18)',
            },
        },
    },

    plugins: [forms],
};
