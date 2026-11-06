import { Controller } from '@hotwired/stimulus';

// Ajoute un bouton « Copier » à chaque bloc de code du conteneur.
export default class extends Controller {
    connect() {
        this.element.querySelectorAll('pre.code-block').forEach((pre) => {
            if (pre.parentElement.classList.contains('code-wrap')) { return; }
            const wrap = document.createElement('div');
            wrap.className = 'code-wrap';
            pre.parentNode.insertBefore(wrap, pre);
            wrap.appendChild(pre);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-secondary btn-sm code-copy';
            button.textContent = 'Copier';
            button.addEventListener('click', () => this.copy(pre, button));
            wrap.appendChild(button);
        });
    }

    async copy(pre, button) {
        try {
            await navigator.clipboard.writeText(pre.innerText);
            button.textContent = 'Copié';
        } catch (e) {
            button.textContent = 'Copie impossible';
        }
        setTimeout(() => { button.textContent = 'Copier'; }, 2000);
    }
}
