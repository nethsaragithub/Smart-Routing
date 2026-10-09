import { gsap } from 'gsap';

let curtain = null;
let parts = null;
let safety = null;
let idle = null;

/**
 * Page transition. The curtain is in the HTML: the page behind it blurred and a
 * bus parked mid-screen. On load the bus pulls away to the right as the blur
 * clears; before following a same-site link it drives in from the left and
 * pulls up in the middle while the page blurs.
 *
 * Skipped (normal navigation): new tabs, modified clicks, downloads, exports,
 * in-page anchors, other sites, and anything another handler already prevented.
 */
export function initTransitions(enabled) {
    curtain = document.querySelector('[data-curtain]');
    if (!curtain) return null;

    // The script has taken over; cancel the CSS failsafe.
    curtain.style.animation = 'none';

    if (!enabled) {
        gsap.set(curtain, { autoAlpha: 0 });
        return null;
    }

    parts = {
        blur: curtain.querySelector('[data-curtain-blur]'),
        road: curtain.querySelector('[data-curtain-road]'),
        bus: curtain.querySelector('[data-curtain-bus]'),
        body: curtain.querySelector('[data-curtain-body]'),
        wheels: curtain.querySelectorAll('[data-curtain-wheel]'),
        trail: curtain.querySelector('[data-curtain-trail]'),
    };

    document.addEventListener('click', onClick);

    // Restored from the back/forward cache with the curtain still closed.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) reveal(0);
    });

    return reveal();
}

/** Turn transitions off at runtime (the header "animations" toggle). */
export function disableTransitions() {
    document.removeEventListener('click', onClick);
    if (curtain) gsap.set(curtain, { autoAlpha: 0 });
}

export function enableTransitions() {
    if (!curtain || !parts) return;
    document.removeEventListener('click', onClick);
    document.addEventListener('click', onClick);
}

/** Distance from the parked spot to just past either edge of the screen. */
const offscreen = () => window.innerWidth / 2 + parts.bus.offsetWidth / 2 + 40;

function spinWheels(turnsPerSecond) {
    gsap.killTweensOf(parts.wheels);
    if (!turnsPerSecond) return;
    gsap.to(parts.wheels, { rotation: '+=360', transformOrigin: '50% 50%', duration: 1 / turnsPerSecond, ease: 'none', repeat: -1 });
}

function stopIdle() {
    idle?.kill();
    idle = null;
    gsap.set(parts.body, { y: 0 });
}

function reveal(duration = 0.8) {
    clearTimeout(safety);
    stopIdle();

    if (!duration) {
        spinWheels(0);
        gsap.set(curtain, { autoAlpha: 0 });
        return null;
    }

    spinWheels(3);

    return gsap.timeline({ onComplete: () => spinWheels(0) })
        .set(curtain, { autoAlpha: 1 })
        .set(parts.bus, { x: 0 })
        .set(parts.trail, { opacity: 0.35, x: 0 })
        // Squat back as it pulls away, then level out.
        .fromTo(parts.body, { rotation: -2, transformOrigin: '30% 100%' }, { rotation: 0, duration: 0.4, ease: 'power2.out' }, 0)
        .to(parts.bus, { x: offscreen, duration, ease: 'power2.in' }, 0)
        .to(parts.blur, { opacity: 0, duration: duration * 0.8, ease: 'power1.inOut' }, duration * 0.2)
        .to(parts.road, { opacity: 0, duration: duration * 0.6 }, duration * 0.3)
        .set(curtain, { autoAlpha: 0 })
        .set([parts.blur, parts.road], { clearProps: 'opacity' });
}

function leave(go) {
    stopIdle();
    spinWheels(3.5);

    gsap.timeline({
        onComplete: () => {
            spinWheels(0.6);
            // Engine running at the stop while the next page loads.
            idle = gsap.to(parts.body, { y: -1, duration: 0.12, ease: 'sine.inOut', repeat: -1, yoyo: true });
            go();
        },
    })
        .set(curtain, { autoAlpha: 1 })
        .set(parts.bus, { x: () => -offscreen() })
        .set(parts.blur, { opacity: 0 })
        .set(parts.road, { opacity: 0 })
        .set(parts.body, { rotation: 0, transformOrigin: '70% 100%' })
        .to(parts.blur, { opacity: 1, duration: 0.45, ease: 'power1.out' }, 0)
        .to(parts.road, { opacity: 0.5, duration: 0.3 }, 0.05)
        .to(parts.bus, { x: 0, duration: 0.6, ease: 'power3.out' }, 0)
        .to(parts.trail, { opacity: 0, x: 10, duration: 0.3 }, 0.35)
        // Nose dips as it brakes, then settles.
        .to(parts.body, { rotation: 1.5, duration: 0.18, ease: 'power1.out' }, 0.42)
        .to(parts.body, { rotation: 0, duration: 0.35, ease: 'back.out(3)' }, 0.6);

    // If the browser stays on this page (e.g. the link was a file download), drive on again.
    safety = setTimeout(() => reveal(0.7), 5000);
}

function onClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const link = event.target.closest('a[href]');
    if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-transition')) return;
    if (link.target && link.target !== '_self') return;

    const href = link.getAttribute('href');
    if (!href || href.startsWith('#')) return;

    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin) return;
    if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
    if (/\/export(\/|$)/.test(url.pathname)) return;

    event.preventDefault();
    leave(() => location.assign(url.href));
}
