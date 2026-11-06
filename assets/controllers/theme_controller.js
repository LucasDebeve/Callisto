import { Controller } from '@hotwired/stimulus';
import { read, write } from './storage.js';

// Bouton de bascule rapide : appareil → clair → sombre.
export default class extends Controller {
    static targets = ['label'];

    connect() { this.render(); }

    cycle() {
        const order = ['auto', 'light', 'dark'];
        const current = read('callisto.theme', 'auto');
        const next = order[(order.indexOf(current) + 1) % order.length];
        apply(next);
        this.render();
    }

    render() {
        const labels = { auto: 'Thème : appareil', light: 'Thème : clair', dark: 'Thème : sombre' };
        const value = read('callisto.theme', 'auto');
        if (this.hasLabelTarget) { this.labelTarget.textContent = labels[value] ?? labels.auto; }
        this.element.setAttribute('aria-label', (labels[value] ?? labels.auto) + '. Changer de thème');
    }
}

export function apply(theme) {
    const root = document.documentElement;
    if (theme === 'light' || theme === 'dark') { root.setAttribute('data-theme', theme); write('callisto.theme', theme); }
    else { root.removeAttribute('data-theme'); write('callisto.theme', null); }
}
