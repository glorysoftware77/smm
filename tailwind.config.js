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
                    50: '#fdf4f6',
                    100: '#f9e8ec',
                    200: '#f2d0d9',
                    300: '#e8a8b8',
                    400: '#d46b84',
                    500: '#c45c74',
                    600: '#b34d66',
                    700: '#8f3c52',
                    800: '#6e3041',
                    900: '#4e2431',
                    950: '#2a141b',
                },
                surface: {
                    DEFAULT: '#121826',
                    raised: '#1A2230',
                    muted: '#222B3A',
                    border: '#2A3444',
                },
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                soft: '0 0 0 1px rgba(255,255,255,0.04), 0 16px 40px -20px rgba(0,0,0,0.55)',
            },
        },
    },

    plugins: [forms],
};
