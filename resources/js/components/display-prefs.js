import { isDark, motionEnabled, store } from '../lib/prefs';

const root = document.documentElement;

/**
 * Header toggles for night mode and animations.
 * Night mode expands as a circle from the button where the View Transitions API exists.
 */
export default () => ({
    dark: isDark(),
    motion: !root.classList.contains('motion-off'),

    toggleTheme(event) {
        const apply = () => {
            this.dark = !this.dark;
            root.classList.toggle('dark', this.dark);
            store('theme', this.dark ? 'dark' : 'light');
            window.dispatchEvent(new CustomEvent('theme:change', { detail: { dark: this.dark } }));
        };

        if (!document.startViewTransition || !motionEnabled()) {
            apply();
            return;
        }

        // Keyboard clicks report 0,0: start from the button centre instead.
        const box = event.currentTarget.getBoundingClientRect();
        const x = event.detail ? event.clientX : box.left + box.width / 2;
        const y = event.detail ? event.clientY : box.top + box.height / 2;
        const radius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));

        const transition = document.startViewTransition(async () => {
            apply();
            await this.$nextTick();
        });

        transition.ready.then(() => {
            root.animate(
                { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`] },
                { duration: 650, easing: 'cubic-bezier(.65, 0, .35, 1)', pseudoElement: '::view-transition-new(root)' },
            );
        });
    },

    toggleMotion() {
        this.motion = !this.motion;
        root.classList.toggle('motion-off', !this.motion);
        store('motion', this.motion ? 'on' : 'off');
        window.dispatchEvent(new CustomEvent('motion:change', { detail: { enabled: this.motion } }));
    },
});
