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
                    300: '#e5a8b8',
                    400: '#d17891',
                    500: '#b84d6b',
                    600: '#9e3d55',
                    700: '#7b2d42',
                    800: '#682938',
                    900: '#592633',
                    950: '#321117',
                },
                surface: {
                    DEFAULT: '#141012',
                    raised: '#1c1719',
                    muted: '#241e21',
                    border: '#34292e',
                },
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                soft: '0 0 0 1px rgba(255,255,255,0.04), 0 12px 40px -16px rgba(0,0,0,0.65)',
            },
        },
    },

    plugins: [forms],
};
