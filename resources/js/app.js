import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

import './echo';

if (document.getElementById('room-app')) {
    import('./room');
}
