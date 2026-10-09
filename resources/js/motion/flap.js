import { gsap } from 'gsap';

const DIGITS = '0123456789';
const LETTERS = 'ABCDEFGHJKLMNPRSTUVWXYZ';

const randomLike = (char) => {
    if (/\d/.test(char)) return DIGITS[(Math.random() * 10) | 0];
    if (/[a-z]/i.test(char)) return LETTERS[(Math.random() * LETTERS.length) | 0];
    return char;
};

/**
 * Split-flap "destination board" effect: characters tumble through random
 * glyphs and settle left to right on the real text. The element's final text is
 * always restored exactly, and its width is held so the layout doesn't jitter.
 */
export function flap(el, { delay = 0, duration = 0.7, rate = 0.045 } = {}) {
    const final = el.textContent;

    // Only plain text nodes; never touch anything Alpine renders.
    if (!final.trim() || el.children.length || el.closest('[x-text], template')) return null;

    const chars = [...final];
    const width = el.getBoundingClientRect().width;
    const state = { p: 0 };
    let lastTick = -1;

    return gsap.to(state, {
        p: 1,
        delay,
        duration,
        ease: 'none',
        onStart() {
            el.style.minWidth = `${width}px`;
        },
        onUpdate() {
            const tick = Math.floor(this.time() / rate);
            if (tick === lastTick) return;
            lastTick = tick;

            const settled = Math.floor(state.p * (chars.length + 1));
            el.textContent = chars.map((c, i) => (i < settled ? c : randomLike(c))).join('');
        },
        onComplete() {
            el.textContent = final;
            el.style.minWidth = '';
        },
        onInterrupt() {
            el.textContent = final;
            el.style.minWidth = '';
        },
    });
}
