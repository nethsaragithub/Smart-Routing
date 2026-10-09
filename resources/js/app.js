import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

// Fonts are bundled so the system works on depot PCs without internet.
import '@fontsource/public-sans/400.css';
import '@fontsource/public-sans/500.css';
import '@fontsource/public-sans/600.css';
import '@fontsource/public-sans/700.css';
import '@fontsource/barlow-condensed/500.css';
import '@fontsource/barlow-condensed/600.css';
import '@fontsource/barlow-condensed/700.css';
import 'leaflet/dist/leaflet.css';

import routeEditor from './components/route-editor';
import routeMap from './components/route-map';
import scheduleForm from './components/schedule-form';
import chart from './components/chart';
import liveBoard from './components/live-board';
import displayPrefs from './components/display-prefs';
import { startMotion } from './motion';

Alpine.plugin(collapse);

Alpine.data('routeEditor', routeEditor);
Alpine.data('routeMap', routeMap);
Alpine.data('scheduleForm', scheduleForm);
Alpine.data('chart', chart);
Alpine.data('liveBoard', liveBoard);
Alpine.data('displayPrefs', displayPrefs);

window.Alpine = Alpine;
Alpine.start();

// After Alpine, so the motion layer sees the page as Alpine has rendered it.
startMotion();
