import { gsap } from 'gsap';
import { motionEnabled } from '../lib/prefs';

const SPOT = { panel: 0.07, tile: 0.12 };

/**
 * Pointer feedback: a ripple from where a button is pressed, primary buttons
 * that lean towards the cursor, and an amber spotlight that follows the pointer
 * across panels. All delegated, so content swapped in later (the live board)
 * gets the same behaviour. Each handler checks the motion setting at event time.
 */
export function initInteractions() {
    document.addEventListener('pointerdown', ripple, { passive: true });
    document.addEventListener('pointermove', onMove, { passive: true });
    document.addEventListener('pointerover', onOver, { passive: true });
    document.addEventListener('pointerout', onOut, { passive: true });
}

function ripple(event) {
    if (!motionEnabled() || event.button !== 0) return;
    const btn = event.target.closest('.btn');
    if (!btn || btn.disabled) return;

    const box = btn.getBoundingClientRect();
    const size = Math.max(box.width, box.height) * 2.2;
    const dot = document.createElement('span');
    dot.className = 'ripple';
    dot.setAttribute('aria-hidden', 'true');
    Object.assign(dot.style, {
        width: `${size}px`,
        height: `${size}px`,
        left: `${event.clientX - box.left - size / 2}px`,
        top: `${event.clientY - box.top - size / 2}px`,
    });
    btn.appendChild(dot);

    gsap.fromTo(dot, { scale: 0, opacity: 0.28 }, { scale: 1, opacity: 0, duration: 0.65, ease: 'power2.out', onComplete: () => dot.remove() });
}

/* Magnetic primary buttons */
const magnets = new WeakMap();

function magnet(btn) {
    if (!magnets.has(btn)) {
        magnets.set(btn, {
            x: gsap.quickTo(btn, 'x', { duration: 0.35, ease: 'power3.out' }),
            y: gsap.quickTo(btn, 'y', { duration: 0.35, ease: 'power3.out' }),
        });
    }
    return magnets.get(btn);
}

/* Spotlight surfaces */
let frame = 0;
let pending = null;

function onMove(event) {
    if (!motionEnabled() || event.pointerType === 'touch') return;
    pending = event;
    if (!frame) frame = requestAnimationFrame(paint);
}

function paint() {
    frame = 0;
    const event = pending;
    if (!event) return;

    const surfaces = event.target.closest?.('.panel, .stat-tile') ? [event.target.closest('.stat-tile'), event.target.closest('.panel')] : [];
    surfaces.forEach((el) => {
        if (!el) return;
        const box = el.getBoundingClientRect();
        el.style.setProperty('--mx', `${event.clientX - box.left}px`);
        el.style.setProperty('--my', `${event.clientY - box.top}px`);
    });

    const btn = event.target.closest?.('.btn-primary');
    if (btn && !btn.disabled) {
        const box = btn.getBoundingClientRect();
        const m = magnet(btn);
        m.x(((event.clientX - box.left) / box.width - 0.5) * 10);
        m.y(((event.clientY - box.top) / box.height - 0.5) * 8);
    }
}

function onOver(event) {
    if (!motionEnabled() || event.pointerType === 'touch') return;
    light(event.target.closest('.stat-tile'), SPOT.tile, event.relatedTarget);
    light(event.target.closest('.panel'), SPOT.panel, event.relatedTarget);
}

function onOut(event) {
    light(event.target.closest('.stat-tile'), 0, event.relatedTarget);
    light(event.target.closest('.panel'), 0, event.relatedTarget);

    const btn = event.target.closest('.btn-primary');
    if (btn && !btn.contains(event.relatedTarget) && magnets.has(btn)) {
        gsap.to(btn, { x: 0, y: 0, duration: 0.6, ease: 'elastic.out(1, 0.4)', clearProps: 'transform' });
        magnets.delete(btn);
    }
}

/** Fade a surface's spotlight in or out, ignoring moves between its own children. */
function light(el, amount, related) {
    if (!el || (related && el.contains(related))) return;
    gsap.to(el, { '--spot': amount, duration: amount ? 0.35 : 0.6, ease: 'power2.out', overwrite: 'auto' });
}
