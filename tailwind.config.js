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
                surface: {
                    DEFAULT: '#FFFFFF',
                    raised: '#F5F6F8',
                    muted: '#EEF0F3',
                    border: '#E4E7EC',
                },
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                soft: '0 1px 2px rgba(26, 29, 35, 0.06), 0 8px 24px -12px rgba(26, 29, 35, 0.12)',
            },
        },
    },

    plugins: [forms],
};
