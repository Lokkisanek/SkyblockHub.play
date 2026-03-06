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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                minecraft: ['"Minecraft"', 'monospace'],
            },
            colors: {
                surface: {
                    900: '#101010',
                    800: '#1a1a20',
                    700: '#232328',
                    600: '#303030',
                    500: '#3a3a42',
                    400: '#4a4a52',
                },
                border: {
                    DEFAULT: '#303030',
                    light: '#404048',
                },
                rarity: {
                    common:    '#FFFFFF',
                    uncommon:  '#55FF55',
                    rare:      '#5555FF',
                    epic:      '#AA00AA',
                    legendary: '#FFAA00',
                    mythic:    '#FF55FF',
                    divine:    '#55FFFF',
                },
                profit:  '#55FF55',
                loss:    '#FF5555',
                neutral: '#AAAAAA',
            },
            borderRadius: {
                sm: '2px',
            },
        },
    },

    plugins: [forms],
};
