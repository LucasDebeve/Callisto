import { Controller } from '@hotwired/stimulus';

// Affiche en direct les règles du mot de passe respectées (le serveur reste seul juge).
export default class extends Controller {
    static targets = ['input', 'confirm', 'rule'];
    static values = { username: String, min: Number };

    check() {
        const value = this.inputTarget.value;
        const classes = [/\p{L}/u, /\d/, /[^\p{L}\d]/u].filter((r) => r.test(value)).length;
        const state = {
            longueur: value.length >= this.minValue,
            sortes: classes >= 2,
            identifiant: value.length > 0 && !(this.usernameValue.length >= 3 && value.toLowerCase().includes(this.usernameValue.toLowerCase())),
            identiques: this.hasConfirmTarget && value.length > 0 && value === this.confirmTarget.value,
        };
        this.ruleTargets.forEach((li) => {
            const ok = state[li.dataset.rule] === true;
            li.dataset.ok = ok ? 'true' : 'false';
            const mark = li.querySelector('[data-mark]');
            if (mark) { mark.textContent = ok ? 'respectée' : 'à respecter'; }
        });
    }
}
