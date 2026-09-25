import './bootstrap';
import Alpine from 'alpinejs';
import mapPicker from './components/map-picker';

Alpine.data('mapPicker', mapPicker);

window.Alpine = Alpine;
Alpine.start();
