import { Controller } from '@hotwired/stimulus';

// Tableau de saisie : un clic pour valider ou retirer, enregistrement sans rechargement (EP3, EP4).
export default class extends Controller {
    static targets = ['date', 'indicator', 'drawer', 'drawerTitle', 'drawerStudent', 'drawerItem', 'drawerDate', 'drawerComment', 'drawerState'];
    static values = { url: String, token: String };

    connect() {
        // Les boutons de détail (date et commentaire) n'ont de sens qu'avec JavaScript.
        this.element.querySelectorAll('[data-entry-grid-detail]').forEach((b) => { b.hidden = false; });
    }

    toggle(event) {
        const button = event.currentTarget;
        const form = button.closest('form');
        if (!form) { return; }
        event.preventDefault();
        const validated = button.getAttribute('aria-pressed') === 'true';
        this.send(button, {
            student: button.dataset.student,
            item: button.dataset.item,
            action: validated ? 'retirer' : 'valider',
            date: this.hasDateTarget ? this.dateTarget.value : '',
            comment: '',
        });
    }

    detail(event) {
        event.preventDefault();
        const b = event.currentTarget;
        this.current = this.element.querySelector(`.cell-toggle[data-student="${b.dataset.student}"][data-item="${b.dataset.item}"]`);
        this.drawerStudentTarget.textContent = b.dataset.studentName;
        this.drawerItemTarget.textContent = b.dataset.itemTitle;
        this.drawerDateTarget.value = this.hasDateTarget ? this.dateTarget.value : '';
        this.drawerCommentTarget.value = '';
        this.drawerStateTarget.textContent = this.current && this.current.getAttribute('aria-pressed') === 'true' ? 'Actuellement validé' : 'Actuellement non validé';
        this.drawerTarget.showModal();
    }

    closeDrawer() { this.drawerTarget.close(); }

    saveDetail(event) {
        event.preventDefault();
        const action = event.submitter && event.submitter.value === 'retirer' ? 'retirer' : 'valider';
        if (!this.current) { return; }
        this.send(this.current, {
            student: this.current.dataset.student,
            item: this.current.dataset.item,
            action,
            date: this.drawerDateTarget.value,
            comment: this.drawerCommentTarget.value,
        });
        this.drawerTarget.close();
    }

    async send(button, data) {
        button.classList.add('is-saving');
        this.setIndicator('saving', 'Enregistrement…');
        const body = new URLSearchParams({ ...data, _token: this.tokenValue });
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
                body,
                credentials: 'same-origin',
            });
            if (!response.ok) { throw new Error(String(response.status)); }
            const result = await response.json();
            this.render(button, result);
            this.setIndicator('saved', 'Enregistré');
        } catch (e) {
            button.classList.add('is-error');
            this.setIndicator('error', 'Échec de l\'enregistrement. Vérifie ta connexion et réessaie.');
        } finally {
            button.classList.remove('is-saving');
        }
    }

    render(button, result) {
        button.classList.remove('is-error');
        button.setAttribute('aria-pressed', result.validated ? 'true' : 'false');
        const label = button.querySelector('[data-label]');
        if (label) { label.textContent = result.validated ? (result.date ?? 'Validé') : 'Non validé'; }
        const sr = button.querySelector('[data-sr]');
        if (sr) { sr.textContent = result.validated ? 'validé, cliquer pour retirer' : 'non validé, cliquer pour valider'; }
        const icon = button.querySelector('[data-check]');
        if (icon) { icon.hidden = !result.validated; }
    }

    setIndicator(state, text) {
        if (!this.hasIndicatorTarget) { return; }
        this.indicatorTarget.dataset.state = state;
        this.indicatorTarget.querySelector('[data-text]').textContent = text;
    }
}
