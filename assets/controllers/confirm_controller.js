import { Controller } from '@hotwired/stimulus';

// Demande confirmation avant une action dangereuse (le serveur exige aussi une saisie de confirmation si besoin).
export default class extends Controller {
    static values = { message: String };

    submit(event) {
        if (!window.confirm(this.messageValue || 'Confirmer ?')) { event.preventDefault(); }
    }
}
