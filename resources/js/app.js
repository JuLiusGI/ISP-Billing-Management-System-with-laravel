import './bootstrap';

// Bootstrap 5 component JavaScript (dropdowns, modals, toasts, tooltips...).
// Exposed globally so Blade views can drive components with vanilla JS.
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Dashboard charts. Self-registering: it looks for its canvases and does
// nothing on pages that have none.
import './charts';
