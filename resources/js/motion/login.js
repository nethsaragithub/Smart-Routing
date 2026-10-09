import { gsap } from 'gsap';
import { flap } from './flap';

/**
 * Sign-in screen: the departure board tilts up into place and each row
 * clacks through the split-flap characters, then the form slides in.
 * Rows marked data-blink keep flashing like a real board.
 */
export function revealLogin(tl) {
    const scene = document.querySelector('[data-login]');
    if (!scene) return;

    const board = scene.querySelector('[data-login-board]');
    if (board) {
        tl.from(board, { y: 70, rotationX: 18, opacity: 0, transformPerspective: 1100, transformOrigin: '50% 100%', duration: 1.2, ease: 'expo.out', clearProps: 'all' }, 0.2);

        board.querySelectorAll('[data-board-row]').forEach((row, r) => {
            tl.from(row, { opacity: 0, duration: 0.2 }, 0.45 + r * 0.16);
            row.querySelectorAll('[data-flap]').forEach((cell) => {
                const tween = flap(cell, { duration: 0.9, rate: 0.05 });
                if (tween) tl.add(tween, 0.45 + r * 0.16);
            });
        });

        board.querySelectorAll('[data-blink]').forEach((el) => {
            gsap.to(el, { opacity: 0.35, duration: 0.6, ease: 'steps(1)', repeat: -1, yoyo: true, repeatDelay: 0.6, delay: 2 });
        });
    }

    route(tl, scene.querySelector('[data-login-route]'));

    tl.from(scene.querySelectorAll('[data-login-intro]'), { x: -24, opacity: 0, duration: 0.8, stagger: 0.1, ease: 'expo.out', clearProps: 'all' }, 0.3);
    tl.from(scene.querySelectorAll('[data-login-form] > :not(form, h1), [data-login-form] form > :not([type="hidden"])'), {
        y: 24, opacity: 0, duration: 0.8, stagger: 0.07, ease: 'expo.out', clearProps: 'all',
    }, 0.35);
}

/**
 * The little route under the board: the line draws from the depot gate, the
 * stops pop in, then a bus runs it stop by stop, dwelling at each one.
 */
function route(tl, svg) {
    if (!svg) return;
    const path = svg.querySelector('[data-route-path]');
    const stops = [...svg.querySelectorAll('[data-route-stop]')];
    const bus = svg.querySelector('[data-route-bus]');

    tl.from(path, { drawSVG: 0, duration: 1.6, ease: 'power2.inOut' }, 0.8);
    tl.from(stops, { scale: 0, transformOrigin: '50% 50%', duration: 0.45, stagger: 0.12, ease: 'back.out(3)' }, 1.2);
    tl.from(svg.querySelectorAll('[data-route-label]'), { opacity: 0, y: 6, duration: 0.5, stagger: 0.12 }, 1.3);

    if (!bus) return;
    const marks = stops.map((s) => Number(s.dataset.routeStop));
    const run = gsap.timeline({ repeat: -1, repeatDelay: 1.2, paused: true });
    marks.slice(1).forEach((end, i) => {
        run.to(bus, {
            motionPath: { path, align: path, alignOrigin: [0.5, 0.5], autoRotate: true, start: marks[i], end },
            duration: 1.1 + (end - marks[i]) * 3,
            ease: 'power2.inOut',
        }).to({}, { duration: 0.7 });
    });
    run.to(bus, { opacity: 0, duration: 0.4 }).set(bus, { opacity: 1 });

    gsap.set(bus, { visibility: 'visible' });
    tl.from(bus, { opacity: 0, duration: 0.4, onStart: () => run.play() }, 2.2);
}

/**
 * Keep any [data-live-clock] showing the depot's current time (HH:MM).
 * Counts on from the server's clock and timezone (data-now ms, data-offset minutes),
 * so a PC with a wrong clock or timezone still shows depot time.
 */
export function liveClocks() {
    const clocks = document.querySelectorAll('[data-live-clock][data-now]');
    if (!clocks.length) return;

    const loaded = Date.now();
    const serverNow = Number(clocks[0].dataset.now);
    const offset = Number(clocks[0].dataset.offset || 0) * 60_000;

    const tick = () => {
        const now = new Date(serverNow + (Date.now() - loaded) + offset);
        const label = `${String(now.getUTCHours()).padStart(2, '0')}:${String(now.getUTCMinutes()).padStart(2, '0')}`;
        clocks.forEach((el) => {
            if (el.textContent !== label) el.textContent = label;
        });
    };
    tick();
    setInterval(tick, 10_000);
}
