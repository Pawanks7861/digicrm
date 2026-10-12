const defaultTheme = require('tailwindcss/defaultTheme');
const forms = require('@tailwindcss/forms');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                canvas: '#F6EFD9',
                mist: '#FFF3E4',
                sage: '#F8F1DC',
                card: '#FFFCF7',
                ink: '#1C1C1C',
                mute: '#8A8178',
                accent: '#E8883A',
                rail: '#3A332C',
                sun: '#F3D98A',
                lagoon: '#C4A15A',
                haze: '#F3EADF',
                line: '#E8DCC8',
            },
            borderRadius: {
                card: '1.5rem',
            },
            boxShadow: {
                float: '0 10px 30px rgba(168, 132, 62, 0.08)',
            },
        },
    },

    plugins: [forms],
};
