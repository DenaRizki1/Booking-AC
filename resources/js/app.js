import './bootstrap';
import Alpine from 'alpinejs';
import mapPicker from './components/map-picker';
import orderUnits from './components/order-units';

Alpine.data('mapPicker', mapPicker);
Alpine.data('orderUnits', orderUnits);

window.Alpine = Alpine;
Alpine.start();
