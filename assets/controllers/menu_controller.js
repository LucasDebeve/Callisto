import { Controller } from '@hotwired/stimulus';

// Menu replié sur téléphone.
export default class extends Controller {
    static targets = ['nav', 'button'];

    toggle() {
        const open = this.navTarget.classList.toggle('is-open');
        this.buttonTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
}
