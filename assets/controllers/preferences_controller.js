import { Controller } from '@hotwired/stimulus';
import { read, write } from './storage.js';
import { apply } from './theme_controller.js';

// Préférences locales : thème, taille du texte, vue constellation ou liste. Rien n'est envoyé au serveur.
export default class extends Controller {
    static targets = ['theme', 'size', 'view', 'status'];

    connect() {
        this.check(this.themeTargets, read('callisto.theme', 'auto'));
        this.check(this.sizeTargets, read('callisto.textSize', 'normal'));
        this.check(this.viewTargets, read('callisto.view', 'carte'));
    }

    check(inputs, value) { inputs.forEach((i) => { i.checked = i.value === value; }); }

    theme(event) { apply(event.target.value); this.saved(); }

    size(event) {
        const value = event.target.value;
        if (value === 'normal') { document.documentElement.removeAttribute('data-text-size'); write('callisto.textSize', null); }
        else { document.documentElement.setAttribute('data-text-size', value); write('callisto.textSize', value); }
        this.saved();
    }

    view(event) { write('callisto.view', event.target.value); this.saved(); }

    saved() { if (this.hasStatusTarget) { this.statusTarget.textContent = 'Préférence enregistrée dans ce navigateur.'; } }
}
