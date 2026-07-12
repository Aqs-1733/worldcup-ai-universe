/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      fontFamily: { sans: ['Inter', 'PingFang SC', 'Microsoft YaHei', 'system-ui', 'sans-serif'] },
      colors: { pitch: '#071a16', neon: '#3fffb5', cyanai: '#52c7ff', ink: '#08111d' },
      boxShadow: { glow: '0 0 40px rgba(63,255,181,.15)' },
      keyframes: {
        float: { '0%,100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-8px)' } },
        pulseLine: { '0%,100%': { opacity: '.25' }, '50%': { opacity: '.8' } }
      },
      animation: { float: 'float 4s ease-in-out infinite', pulseLine: 'pulseLine 2.4s ease-in-out infinite' }
    },
  },
  plugins: [],
}
