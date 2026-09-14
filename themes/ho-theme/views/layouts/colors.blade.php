{{--
 | Hostorio palette as the built-in fallback.
 |
 | theme() reads saved values out of the settings table and falls back to the
 | default passed here, so the values below are what renders on a fresh install
 | where nobody has opened Settings -> Theme yet. Anything set through the admin
 | colour pickers still wins over these.
 |
 | Navy #01257D, orange #FF7800, and the greys/semantics from the brand palette.
--}}
<style>
    :root {
        /* Branding Colors (Light) — orange carries actions, navy is chrome */
        --color-primary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('primary', '28 100% 50%'))) }};
        --color-secondary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('secondary', '223 98% 25%'))) }};

        /* Neutral Colors - Borders, Accents... (Light) */
        --color-neutral: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('neutral', '0 0% 87%'))) }};

        /* Text Colors (Light) */
        --color-base: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('base', '0 0% 20%'))) }};
        --color-muted: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('muted', '0 0% 40%'))) }};
        --color-inverted: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('inverted', '0 0% 100%'))) }};

        /* State Colors — from the brand palette's green/red/amber/grey/blue */
        --color-success: 130 67% 47%;
        --color-error: 0 100% 50%;
        --color-warning: 34 100% 50%;
        --color-inactive: 0 0% 60%;
        --color-info: 216 85% 49%;

        /* Background Colors (Light) */
        --color-background: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('background', '0 0% 100%'))) }};
        --color-background-secondary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('background-secondary', '210 17% 98%'))) }};
    }

    .dark {
        /* Navy is unreadable on a dark ground, so secondary promotes the
           palette's brighter blue #1368E7 here. */
        --color-primary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-primary', '34 100% 50%'))) }};
        --color-secondary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-secondary', '216 85% 58%'))) }};

        /* Neutral Colors - Borders, Accents... (Dark) */
        --color-neutral: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-neutral', '222 20% 22%'))) }};

        /* Text Colors (Dark) */
        --color-base: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-base', '210 17% 95%'))) }};
        --color-muted: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-muted', '215 12% 62%'))) }};
        --color-inverted: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-inverted', '0 0% 100%'))) }};

        /* Background Colors (Dark) */
        --color-background: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-background', '222 35% 9%'))) }};
        --color-background-secondary: {{ str_replace(',', '', preg_replace('/^hsl\((.+)\)$/', '$1', theme('dark-background-secondary', '222 30% 13%'))) }};
    }
</style>
