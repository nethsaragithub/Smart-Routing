import { gsap } from 'gsap';

let curtain = null;
let safety = null;

/**
 * Page-transition wipe. The curtain is in the HTML covering the page; this slides
 * it away on load and back in before following a same-site link.
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
    if (!curtain) return;
    document.removeEventListener('click', onClick);
    document.addEventListener('click', onClick);
}

function reveal(duration = 0.65) {
    clearTimeout(safety);
    const logo = curtain.querySelector('[data-curtain-logo]');

    return gsap.timeline()
        .set(curtain, { autoAlpha: 1, xPercent: 0 })
        .to(logo, { scale: 0.85, autoAlpha: 0, duration: Math.min(duration, 0.2), ease: 'power2.in' })
        .to(curtain, { xPercent: 100, duration, ease: 'expo.inOut' }, '<0.05')
        .set(curtain, { autoAlpha: 0 });
}

function leave(go) {
    const logo = curtain.querySelector('[data-curtain-logo]');

    gsap.timeline({ onComplete: go })
        .set(curtain, { autoAlpha: 1, xPercent: -100 })
        .set(logo, { autoAlpha: 0, scale: 0.85 })
        .to(curtain, { xPercent: 0, duration: 0.45, ease: 'expo.in' })
        .to(logo, { autoAlpha: 1, scale: 1, duration: 0.25, ease: 'back.out(2)' }, '-=0.08');

    // If the browser stays on this page (e.g. the link was a file download), open up again.
    safety = setTimeout(() => reveal(0.5), 5000);
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
