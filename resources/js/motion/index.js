import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { MotionPathPlugin } from 'gsap/MotionPathPlugin';
import { motionEnabled } from '../lib/prefs';
import { initBackdrop } from './backdrop';
import { initInteractions } from './interactions';
import { liveClocks, revealLogin } from './login';
import { flaps, headerScroll, revealPage, rows } from './reveal';
import { disableTransitions, enableTransitions, initTransitions } from './transitions';

gsap.registerPlugin(ScrollTrigger, SplitText, DrawSVGPlugin, MotionPathPlugin);
// Shared sequences target optional parts (sidebar, flashes); absent ones are expected.
gsap.config({ nullTargetWarn: false });

const INTRO_KEY = 'srmss:intro-seen';

function firstVisitOfSession() {
    try {
        if (sessionStorage.getItem(INTRO_KEY)) return false;
        sessionStorage.setItem(INTRO_KEY, '1');
        return true;
    } catch {
        return false;
    }
}

/**
 * Motion layer for every page. Purely presentational: it never changes what
 * the page does, only how it arrives. With reduced motion (OS setting) or the
 * header toggle off, the page is shown as-is and the backdrop stays still.
 */
export async function startMotion() {
    liveClocks();

    const intro = gsap.timeline({ defaults: { ease: 'expo.out' } });
    let stopBackdrop = () => {};
    let ambient = false;

    // Effects that can be switched on mid-page from the header toggle.
    const startAmbient = (options) => {
        stopBackdrop = initBackdrop(options);
        if (ambient) return;
        ambient = true;
        headerScroll();
        initInteractions();
        onBoardRefresh();
    };

    window.addEventListener('motion:change', ({ detail }) => {
        if (detail.enabled && motionEnabled()) {
            enableTransitions();
            startAmbient();
        } else {
            disableTransitions();
            stopBackdrop();
            intro.progress(1);
            gsap.killTweensOf('[data-blink], [data-route-bus]');
            gsap.set('[data-blink]', { clearProps: 'opacity' });
        }
    });

    if (!motionEnabled()) {
        initTransitions(false);
        return;
    }

    // Wait briefly for the bundled fonts so split titles break where they will end up.
    await Promise.race([document.fonts?.ready, new Promise((r) => setTimeout(r, 350))]);

    const firstVisit = firstVisitOfSession();

    initTransitions(true);
    startAmbient({ intro: firstVisit });
    revealLogin(intro);
    revealPage(intro, { firstVisit });
}

/** The dashboard trip board replaces its HTML every minute; animate only real changes. */
function onBoardRefresh() {
    window.addEventListener('content:replaced', (event) => {
        if (!motionEnabled() || !event.detail?.changed) return;
        const scope = event.detail.element;
        rows(scope, { offset: 0 });
        flaps(scope);
    });
}
