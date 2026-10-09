import { gsap } from 'gsap';

const INTERACTIVE = 'a[href], button:not(:disabled), select, input:not([type=hidden]), textarea, label[for], summary, [role=button], [tabindex]:not([tabindex="-1"])';

/**
 * Headlight: a soft amber beam that follows the pointer across the whole page,
 * with a ring marking the cursor itself. The ring swells over anything clickable
 * and dips on press. Mouse and pen only; the cursor stays the system cursor.
 *
 * Returns a stop() function that removes it.
 */
export function initHeadlight() {
    const root = document.createElement('div');
    root.className = 'headlight';
    root.setAttribute('aria-hidden', 'true');
    root.innerHTML = '<span class="headlight-beam"></span><span class="headlight-ring"></span>';
    document.body.appendChild(root);

    const beam = root.firstElementChild;
    const ring = root.lastElementChild;

    // The ring keeps close to the cursor; the beam trails like a lamp swinging round.
    const ringX = gsap.quickTo(ring, 'x', { duration: 0.18, ease: 'power3.out' });
    const ringY = gsap.quickTo(ring, 'y', { duration: 0.18, ease: 'power3.out' });
    const beamX = gsap.quickTo(beam, 'x', { duration: 0.6, ease: 'power3.out' });
    const beamY = gsap.quickTo(beam, 'y', { duration: 0.6, ease: 'power3.out' });

    let visible = false;
    let hovering = false;

    const show = (on) => {
        if (visible === on) return;
        visible = on;
        gsap.to(root, { opacity: on ? 1 : 0, duration: on ? 0.3 : 0.5, ease: 'power2.out', overwrite: 'auto' });
    };

    const onMove = (event) => {
        if (event.pointerType === 'touch') return show(false);
        if (!visible) {
            // Appear where the pointer is instead of sliding in from the corner.
            gsap.set([ring, beam], { x: event.clientX, y: event.clientY });
        }
        ringX(event.clientX);
        ringY(event.clientY);
        beamX(event.clientX);
        beamY(event.clientY);
        show(true);

        const over = Boolean(event.target.closest?.(INTERACTIVE));
        if (over !== hovering) {
            hovering = over;
            root.classList.toggle('is-hovering', over);
            gsap.to(ring, { scale: over ? 1.7 : 1, duration: 0.35, ease: 'back.out(2)', overwrite: 'auto' });
        }
    };

    const onDown = (event) => {
        if (event.pointerType === 'touch') return;
        gsap.to(ring, { scale: hovering ? 1.3 : 0.75, duration: 0.12, ease: 'power2.out', overwrite: 'auto' });
    };

    const onUp = (event) => {
        if (event.pointerType === 'touch') return;
        gsap.to(ring, { scale: hovering ? 1.7 : 1, duration: 0.4, ease: 'back.out(3)', overwrite: 'auto' });
    };

    // Leaving the window (or the tab losing focus) switches the light off.
    const onLeave = (event) => {
        if (!event.relatedTarget) show(false);
    };
    const onBlur = () => show(false);

    document.addEventListener('pointermove', onMove, { passive: true });
    document.addEventListener('pointerdown', onDown, { passive: true });
    document.addEventListener('pointerup', onUp, { passive: true });
    document.addEventListener('pointerout', onLeave, { passive: true });
    window.addEventListener('blur', onBlur);

    return function stop() {
        document.removeEventListener('pointermove', onMove);
        document.removeEventListener('pointerdown', onDown);
        document.removeEventListener('pointerup', onUp);
        document.removeEventListener('pointerout', onLeave);
        window.removeEventListener('blur', onBlur);
        gsap.killTweensOf([root, ring, beam]);
        root.remove();
    };
}
