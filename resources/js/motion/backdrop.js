import { gsap } from 'gsap';

/**
 * Transit-map backdrop: the route lines draw themselves on the first visit of a
 * session, buses run along them continuously, the glows drift, and the layers
 * shift with scroll and the pointer at different depths.
 *
 * Returns a stop() function that freezes everything back to a still map.
 */
export function initBackdrop({ intro = false } = {}) {
    const root = document.querySelector('[data-backdrop]');
    if (!root) return () => {};

    const tweens = [];
    const lines = root.querySelectorAll('.bd-line');
    const stations = root.querySelectorAll('.bd-station');

    if (intro) {
        tweens.push(
            gsap.from(lines, { drawSVG: 0, duration: 2.4, stagger: 0.18, ease: 'power2.inOut', delay: 0.2 }),
            gsap.from(stations, { scale: 0, transformOrigin: '50% 50%', duration: 0.5, stagger: 0.06, ease: 'back.out(3)', delay: 1.4 }),
        );
    }

    root.querySelectorAll('[data-bus]').forEach((bus) => {
        const path = root.querySelector(bus.dataset.bus);
        if (!path) return;

        const tween = gsap.to(bus, {
            motionPath: { path, align: path, alignOrigin: [0.5, 0.5], autoRotate: true },
            duration: Number(bus.dataset.duration) || 30,
            ease: 'none',
            repeat: -1,
            paused: true,
        });
        tween.progress(Number(bus.dataset.offset) || 0);
        gsap.set(bus, { visibility: 'visible' });
        gsap.from(bus, { opacity: 0, duration: 1.2, delay: intro ? 2 : 0.3 });
        tween.play();
        tweens.push(tween);
    });

    root.querySelectorAll('[data-drift]').forEach((glow, i) => {
        tweens.push(gsap.to(glow, {
            x: i % 2 ? -140 : 160,
            y: i % 2 ? -90 : 110,
            scale: 1.15,
            transformOrigin: '50% 50%',
            duration: 16 + i * 7,
            ease: 'sine.inOut',
            yoyo: true,
            repeat: -1,
        }));
    });

    // Parallax: each layer moves by its depth with scroll and pointer.
    const layers = [...root.querySelectorAll('[data-depth]')].map((el) => ({
        depth: Number(el.dataset.depth),
        x: gsap.quickTo(el, 'x', { duration: 1.4, ease: 'power3.out' }),
        y: gsap.quickTo(el, 'y', { duration: 1.4, ease: 'power3.out' }),
    }));

    let px = 0;
    let py = 0;
    let frame = 0;

    const update = () => {
        frame = 0;
        const scroll = window.scrollY;
        layers.forEach(({ depth, x, y }) => {
            x(px * depth * 600);
            y(py * depth * 400 - scroll * depth * 1.6);
        });
    };
    const schedule = () => {
        if (!frame) frame = requestAnimationFrame(update);
    };
    const onPointer = (event) => {
        px = event.clientX / innerWidth - 0.5;
        py = event.clientY / innerHeight - 0.5;
        schedule();
    };

    window.addEventListener('pointermove', onPointer, { passive: true });
    window.addEventListener('scroll', schedule, { passive: true });

    return function stop() {
        window.removeEventListener('pointermove', onPointer);
        window.removeEventListener('scroll', schedule);
        cancelAnimationFrame(frame);
        tweens.forEach((t) => t.progress(t.repeat() ? t.progress() : 1).kill());
        root.querySelectorAll('[data-bus]').forEach((bus) => gsap.set(bus, { visibility: 'hidden' }));
        gsap.set(root.querySelectorAll('[data-depth], [data-drift]'), { clearProps: 'transform' });
    };
}
