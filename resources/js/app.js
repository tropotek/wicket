import 'bootstrap';
import Alpine from 'alpinejs';
import htmx from 'htmx.org';
import lightbox from './components/lightbox.js';
import secretCard from './components/secret-card.js';

window.Alpine = Alpine;
window.htmx = htmx;

// Swap 422 responses too: they carry the form re-rendered with its validation errors.
htmx.config.responseHandling = [
    { code: '204', swap: false },
    { code: '[23]..', swap: true },
    { code: '422', swap: true },
    { code: '[45]..', swap: false, error: true },
    { code: '...', swap: false },
];

Alpine.data('lightbox', lightbox);
Alpine.data('secretCard', secretCard);

Alpine.start();
