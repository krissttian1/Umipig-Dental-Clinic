/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./includes/*.php",
    "./assets/js/*.js"
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Poppins', 'sans-serif'],
      },
      colors: {
        primary: '#2563eb',
        secondary: '#4f46e5',
        accent: '#7c3aed',
        dark: '#1e293b',
        light: '#f8fafc'
      },
    },
  },
  plugins: [],
}
