import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Pindai semua file Blade dan JS agar Tailwind tidak membuang class yang dipakai
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    // Catatan: Dark mode tidak diaktifkan (direncanakan untuk versi 2.0).
    // Tidak perlu konfigurasi darkMode — cukup tidak menggunakan class `dark:`.

    theme: {
        extend: {
            // ─── Palet Warna el Craft ─────────────────────────────────
            // Sumber kebenaran tunggal untuk seluruh token warna
            colors: {
                brand:          '#C17B6F', // Rose Gold — warna utama merek
                brandDark:      '#9E5A4E', // Deeper Rose — state hover
                accent:         '#E8D5C4', // Soft Peach — highlight lembut
                warmBlack:      '#1C1917', // Warm Black — teks utama
                warmGrey:       '#78716C', // Warm Grey — teks sekunder
                warmLightGrey:  '#EDE8E4', // Warm Light Grey — border & divider
                warmCream:      '#FDF8F6', // Warm Cream — background section
            },

            // ─── Border Radius Kustom ─────────────────────────────────
            borderRadius: {
                'btn':  '6px', // Tombol
                'card': '8px', // Kartu produk
                'img':  '8px', // Gambar produk
            },

            // ─── Tipografi ────────────────────────────────────────────
            // Inter sebagai font utama, dengan fallback dari defaultTheme
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};

