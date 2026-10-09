/**
 * Display preferences shared by the motion layer, charts and the header toggles.
 * The classes on <html> are set before first paint by <x-theme-script>.
 */
const root = document.documentElement;
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

export const isDark = () => root.classList.contains('dark');

/** Animations run unless the OS asks for reduced motion or the user switched them off. */
export const motionEnabled = () => !reducedMotion.matches && !root.classList.contains('motion-off');

export function store(key, value) {
    try {
        localStorage.setItem(`srmss:${key}`, value);
    } catch {
        // Private windows can refuse storage; the choice then lasts for this page only.
    }
}

/** Read a design token, e.g. cssVar('--color-ink'). */
export const cssVar = (name) => getComputedStyle(root).getPropertyValue(name).trim();
