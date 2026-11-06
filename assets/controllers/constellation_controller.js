import { Controller } from '@hotwired/stimulus';

// Ouvre le panneau latéral d'une étoile sans recharger la page (le lien fonctionne aussi sans JavaScript).
export default class extends Controller {
    static targets = ['star', 'panel', 'detail'];

    open(event) {
        const code = event.currentTarget.dataset.code;
        const detail = this.detailTargets.find((d) => d.dataset.code === code);
        if (!detail) { return; }
        event.preventDefault();
        this.detailTargets.forEach((d) => { d.hidden = d !== detail; });
        this.starTargets.forEach((s) => s.classList.toggle('is-selected', s.dataset.code === code));
        const url = new URL(window.location.href);
        url.searchParams.set('etoile', code);
        window.history.replaceState({}, '', url);
        const heading = detail.querySelector('h2');
        if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus(); }
    }

    key(event) {
        if (event.key === 'Enter' || event.key === ' ') { this.open(event); }
    }
}
