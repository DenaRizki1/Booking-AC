import './bootstrap';
import Alpine from 'alpinejs';
import mapPicker from './components/map-picker';
import miniMap from './components/mini-map';
import officeMap from './components/office-map';
import orderUnits from './components/order-units';
import priceMatrix from './components/price-matrix';

Alpine.data('mapPicker', mapPicker);
Alpine.data('miniMap', miniMap);
Alpine.data('officeMap', officeMap);
Alpine.data('orderUnits', orderUnits);
Alpine.data('priceMatrix', priceMatrix);

window.Alpine = Alpine;
Alpine.start();
