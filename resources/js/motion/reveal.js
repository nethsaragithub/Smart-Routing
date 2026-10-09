import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
import { flap } from './flap';

const MAX_ROWS = 24;
const MAX_FLAPS = 30;

/**
 * Elements Alpine shows and hides (modals, tabs, collapses) are never touched,
 * and every tween clears its inline styles when done: a leftover transform would
 * become the containing block for fixed modals and break sticky table headers.
 */
const untouchable = (el) => el.closest('[x-show], [x-if], [x-collapse], template, [role="dialog"]');
const rendered = (el) => el.getClientRects().length > 0;
const done = { clearProps: 'opacity,transform,visibility' };

/** The page-load sequence, played onto the given timeline. */
export function revealPage(tl, { firstVisit = false } = {}) {
    sidebar(tl, firstVisit);
    pageHeader(tl);

    document.querySelectorAll('main [role="status"], main [role="alert"]').forEach((el) => {
        if (!untouchable(el) && !el.closest('[data-login]')) tl.from(el, { y: -14, opacity: 0, duration: 0.5, ...done }, 0.35);
    });

    panels(tl);
}

function sidebar(tl, firstVisit) {
    const brand = document.querySelector('aside .route-board');
    const brandFlap = brand && flap(brand, { duration: 0.6 });
    if (brandFlap) tl.add(brandFlap, 0.1);

    if (!firstVisit) return;
    const items = document.querySelectorAll('aside nav li, aside nav > div > div:first-child');
    tl.from(items, { x: -22, opacity: 0, duration: 0.5, stagger: 0.025, ease: 'power3.out', ...done }, 0.15);
}

function pageHeader(tl) {
    const title = document.querySelector('[data-page-title]');
    if (!title) return;

    const split = SplitText.create(title, { type: 'words,chars', mask: 'words', wordsClass: 'split-word' });
    tl.from(split.chars, {
        yPercent: 115,
        rotate: 6,
        duration: 0.8,
        stagger: 0.022,
        ease: 'expo.out',
        onComplete: () => split.revert(),
    }, 0.12);

    const header = title.closest('[data-page-header]');
    if (header) {
        const rest = header.querySelectorAll(':scope > div:first-child > :not([data-page-title]), :scope > div:last-child:not(:first-child) > *');
        tl.from(rest, { y: 14, opacity: 0, duration: 0.6, stagger: 0.05, ease: 'power3.out', ...done }, 0.3);
    }

    // Title eases up and fades a little as the page scrolls under the header.
    gsap.to(title, {
        yPercent: -18,
        opacity: 0.55,
        ease: 'none',
        scrollTrigger: { start: 0, end: 260, scrub: 0.4 },
    });
}

function panels(tl) {
    const all = [...document.querySelectorAll('main .panel')]
        .filter((p) => !untouchable(p) && !p.parentElement.closest('.panel') && rendered(p));

    const inView = [];
    const below = [];
    all.forEach((p) => (p.getBoundingClientRect().top < innerHeight * 0.95 ? inView : below).push(p));

    inView.forEach((panel, i) => tl.add(enter(panel), 0.25 + i * 0.08));

    if (!below.length) return;
    gsap.set(below, { opacity: 0, y: 60 });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            enter(entry.target, { fromCurrent: true });
        });
    }, { rootMargin: '0px 0px -6% 0px' });
    below.forEach((p) => observer.observe(p));

    // Keyboard users tabbing ahead shouldn't land in an invisible panel.
    document.addEventListener('focusin', (event) => {
        const panel = below.find((p) => p.contains(event.target));
        if (panel) {
            observer.unobserve(panel);
            gsap.set(panel, done);
        }
    });
}

/** One panel arriving: the surface, then its stat tiles or table rows, flaps and counters. */
export function enter(panel, { fromCurrent = false } = {}) {
    const tl = gsap.timeline();
    const from = { y: 60, opacity: 0, scale: 0.985 };

    tl[fromCurrent ? 'to' : 'fromTo'](
        panel,
        ...(fromCurrent ? [] : [from]),
        { y: 0, opacity: 1, scale: 1, duration: 0.9, ease: 'expo.out', ...done },
    );

    const tiles = panel.querySelectorAll('.stat-tile');
    if (tiles.length) {
        tl.from(tiles, { y: 26, opacity: 0, duration: 0.7, stagger: 0.07, ease: 'power3.out', ...done }, 0.1);
        panel.querySelectorAll('[data-count]').forEach((el, i) => tl.add(countUp(el), 0.2 + i * 0.07));
    }

    tl.add(rows(panel), 0.15);
    flaps(panel).forEach((t, i) => t && tl.add(t, 0.2 + i * 0.03));

    return tl;
}

/** Table rows slide in one after another (only the first screenful). */
export function rows(scope, { offset = -18 } = {}) {
    const list = [...scope.querySelectorAll('tbody > tr')].filter((r) => !untouchable(r)).slice(0, MAX_ROWS);
    if (!list.length) return gsap.timeline();

    return gsap.from(list, { x: offset, opacity: 0, duration: 0.55, stagger: 0.03, ease: 'power3.out', ...done });
}

export function flaps(scope) {
    return [...scope.querySelectorAll('.route-board')]
        .filter((el) => !untouchable(el))
        .slice(0, MAX_FLAPS)
        .map((el) => flap(el));
}

/** Numbers count up from zero, then the exact original text is put back. */
export function countUp(el) {
    const original = el.textContent;
    const match = original.trim().match(/^(\D*?)(\d[\d,]*(?:\.\d+)?)(.*)$/);
    if (!match) return gsap.timeline();

    const [, prefix, number, suffix] = match;
    const target = Number(number.replace(/,/g, ''));
    const decimals = (number.split('.')[1] || '').length;
    const state = { v: 0 };

    return gsap.to(state, {
        v: target,
        duration: 1.6,
        ease: 'power3.out',
        onUpdate: () => {
            el.textContent = `${prefix}${state.v.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals, useGrouping: number.includes(',') })}${suffix}`;
        },
        onComplete: () => {
            el.textContent = original;
        },
    });
}

/** Thin amber progress line under the sticky header, and its shadow once scrolled. */
export function headerScroll() {
    const header = document.querySelector('[data-app-header]');
    const bar = document.querySelector('[data-scroll-progress]');
    if (!header) return;

    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 6);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    if (bar) {
        gsap.to(bar, {
            scaleX: 1,
            ease: 'none',
            scrollTrigger: { start: 0, end: 'max', scrub: 0.3 },
        });
    }

    // Content can change height (Alpine sections, the live board); keep positions true.
    let timer;
    new ResizeObserver(() => {
        clearTimeout(timer);
        timer = setTimeout(() => ScrollTrigger.refresh(), 250);
    }).observe(document.body);
}
