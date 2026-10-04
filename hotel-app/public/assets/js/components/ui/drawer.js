// Drawer variant of the accessible layer primitive (P04.04).
import { openLayer, closeLayer } from './dialog.js';

export function openDrawer(drawer, options) {
  if (drawer instanceof HTMLElement) drawer.dataset.variant = 'drawer';
  openLayer(drawer, options);
}

export function closeDrawer(drawer, options) {
  closeLayer(drawer, options);
}
