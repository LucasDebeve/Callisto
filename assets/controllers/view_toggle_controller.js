import { Controller } from '@hotwired/stimulus';
import { read, write } from './storage.js';

// Bascule constellation / liste, choix mémorisé dans le navigateur. Sans JavaScript, les deux vues sont affichées.
export default class extends Controller {
    static targets = ['map', 'list', 'button'];

    connect() { this.show(read('callisto.view', window.matchMedia('(max-width: 639px)').matches ? 'liste' : 'carte')); }

    choose(event) {
        const view = event.currentTarget.dataset.view;
        write('callisto.view', view);
        this.show(view);
    }

    show(view) {
        const list = view === 'liste';
        this.mapTarget.hidden = list;
        this.listTarget.hidden = !list;
        this.buttonTargets.forEach((b) => b.setAttribute('aria-pressed', b.dataset.view === view ? 'true' : 'false'));
    }
}
