/** @type {import('tailwindcss').Config} */

function oklchVar(cssVar) {
  return `oklch(var(${cssVar}) / <alpha-value>)`
}

export default {
  darkMode: 'class',
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        border: oklchVar('--border'),
        input: oklchVar('--input'),
        ring: oklchVar('--ring'),
        background: oklchVar('--background'),
        foreground: oklchVar('--foreground'),
        primary: {
          DEFAULT: oklchVar('--primary'),
          foreground: oklchVar('--primary-foreground'),
        },
        secondary: {
          DEFAULT: oklchVar('--secondary'),
          foreground: oklchVar('--secondary-foreground'),
        },
        destructive: {
          DEFAULT: oklchVar('--destructive'),
          foreground: oklchVar('--destructive-foreground'),
        },
        muted: {
          DEFAULT: oklchVar('--muted'),
          foreground: oklchVar('--muted-foreground'),
        },
        accent: {
          DEFAULT: oklchVar('--accent'),
          foreground: oklchVar('--accent-foreground'),
        },
        popover: {
          DEFAULT: oklchVar('--popover'),
          foreground: oklchVar('--popover-foreground'),
        },
        card: {
          DEFAULT: oklchVar('--card'),
          foreground: oklchVar('--card-foreground'),
        },
        sidebar: {
          DEFAULT: oklchVar('--sidebar'),
          foreground: oklchVar('--sidebar-foreground'),
          primary: oklchVar('--sidebar-primary'),
          'primary-foreground': oklchVar('--sidebar-primary-foreground'),
          accent: oklchVar('--sidebar-accent'),
          'accent-foreground': oklchVar('--sidebar-accent-foreground'),
          border: oklchVar('--sidebar-border'),
          ring: oklchVar('--sidebar-ring'),
        },
        chart: {
          1: oklchVar('--chart-1'),
          2: oklchVar('--chart-2'),
          3: oklchVar('--chart-3'),
          4: oklchVar('--chart-4'),
          5: oklchVar('--chart-5'),
        },
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
      },
    },
  },
  plugins: [],
}
