import { Controller } from '@hotwired/stimulus';

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);
const MAX_ZOOM = 3;

/*
 * Constellation vivante :
 *  - entrée progressive, couche après couche, du plus fondamental au plus avancé ;
 *  - survol ou focus d'une étoile : ses prérequis, ses suites et leurs liens s'éclairent, le reste s'estompe ;
 *  - déplacement à la souris ou au doigt avec inertie, zoom (Ctrl + molette, pincement du trackpad, boutons) ;
 *  - choisir une étoile ouvre son panneau sans recharger la page et la caméra glisse jusqu'à elle.
 * Sans JavaScript, chaque étoile reste un lien qui ouvre le même panneau côté serveur.
 * Si l'appareil demande moins d'animations, tout se fait instantanément.
 */
export default class extends Controller {
    static targets = ['star', 'detail', 'svg', 'far', 'link', 'tools', 'hint', 'panel'];

    connect() {
        if (!this.hasSvgTarget) { return; }
        const vb = this.svgTarget.viewBox.baseVal;
        this.base = { x: vb.x, y: vb.y, w: vb.width, h: vb.height };
        this.view = { ...this.base };
        this.pointers = new Map();
        this.neighbours = this.buildNeighbours();

        this.starTargets.forEach((star, n) => {
            star.style.setProperty('--layer', star.dataset.layer || '0');
            star.style.setProperty('--n', String(n));
        });
        if (!reducedMotion()) {
            this.svgTarget.classList.add('is-entering');
            this.enterTimer = window.setTimeout(() => this.svgTarget.classList.remove('is-entering'), 2400);
        }

        if (this.hasToolsTarget) { this.toolsTarget.hidden = false; }
        if (this.hasHintTarget) { this.hintTarget.hidden = false; }

        this.onWheel = this.wheel.bind(this);
        this.onDown = this.pointerDown.bind(this);
        this.onMove = this.pointerMove.bind(this);
        this.onUp = this.pointerUp.bind(this);
        this.svgTarget.addEventListener('wheel', this.onWheel, { passive: false });
        this.svgTarget.addEventListener('pointerdown', this.onDown);
        this.svgTarget.addEventListener('pointermove', this.onMove);
        this.svgTarget.addEventListener('pointerup', this.onUp);
        this.svgTarget.addEventListener('pointercancel', this.onUp);
    }

    disconnect() {
        window.clearTimeout(this.enterTimer);
        cancelAnimationFrame(this.frame);
        if (!this.hasSvgTarget) { return; }
        this.svgTarget.removeEventListener('wheel', this.onWheel);
        this.svgTarget.removeEventListener('pointerdown', this.onDown);
        this.svgTarget.removeEventListener('pointermove', this.onMove);
        this.svgTarget.removeEventListener('pointerup', this.onUp);
        this.svgTarget.removeEventListener('pointercancel', this.onUp);
    }

    // ------------------------------------------------------------ Panneau latéral

    open(event) {
        if (this.dragged) {
            event.preventDefault();
            this.dragged = false;
            return;
        }
        const code = event.currentTarget.dataset.code;
        const detail = this.detailTargets.find((d) => d.dataset.code === code);
        if (!detail) { return; }
        event.preventDefault();
        this.detailTargets.forEach((d) => { d.hidden = d !== detail; });
        this.starTargets.forEach((s) => s.classList.toggle('is-selected', s.dataset.code === code));
        const url = new URL(window.location.href);
        url.searchParams.set('etoile', code);
        window.history.replaceState({}, '', url);

        const star = this.starTargets.find((s) => s.dataset.code === code);
        if (star) { this.glideTo(star, Math.max(this.zoom(), 1.35)); }

        const heading = detail.querySelector('h2');
        if (heading) {
            heading.setAttribute('tabindex', '-1');
            heading.focus({ preventScroll: true });
        }
        if (this.hasPanelTarget && window.matchMedia('(max-width: 1023px)').matches) {
            this.panelTarget.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' });
        }
    }

    // ------------------------------------------------------------ Voisinage

    buildNeighbours() {
        const map = new Map();
        const add = (a, b) => { if (!map.has(a)) { map.set(a, new Set()); } map.get(a).add(b); };
        this.linkTargets.forEach((link) => { add(link.dataset.from, link.dataset.to); add(link.dataset.to, link.dataset.from); });

        return map;
    }

    highlight(event) {
        const code = event.currentTarget.dataset.code;
        const near = this.neighbours.get(code) || new Set();
        this.svgTarget.classList.add('is-focusing');
        this.starTargets.forEach((s) => s.classList.toggle('is-near', s.dataset.code === code || near.has(s.dataset.code)));
        this.linkTargets.forEach((l) => l.classList.toggle('is-near', l.dataset.from === code || l.dataset.to === code));
    }

    unhighlight() {
        this.svgTarget.classList.remove('is-focusing');
        this.starTargets.forEach((s) => s.classList.remove('is-near'));
        this.linkTargets.forEach((l) => l.classList.remove('is-near'));
    }

    focusStar(event) {
        this.highlight(event);
        // Au clavier, la caméra suit l'étoile si l'on est rapproché.
        if (this.zoom() > 1.05) { this.glideTo(event.currentTarget, this.zoom()); }
    }

    // ------------------------------------------------------------ Caméra

    zoom() { return this.base.w / this.view.w; }

    clamp(view) {
        const w = Math.min(this.base.w, Math.max(this.base.w / MAX_ZOOM, view.w));
        const h = w * (this.base.h / this.base.w);
        const slackX = this.base.w * 0.12;
        const slackY = this.base.h * 0.12;
        return {
            w,
            h,
            x: Math.min(this.base.x + this.base.w - w + slackX, Math.max(this.base.x - slackX, view.x)),
            y: Math.min(this.base.y + this.base.h - h + slackY, Math.max(this.base.y - slackY, view.y)),
        };
    }

    apply() {
        const v = this.view;
        this.svgTarget.setAttribute('viewBox', `${v.x.toFixed(1)} ${v.y.toFixed(1)} ${v.w.toFixed(1)} ${v.h.toFixed(1)}`);
        this.svgTarget.classList.toggle('is-zoomed', this.zoom() > 1.02);
        // Parallaxe : le ciel lointain se déplace deux fois moins vite que les étoiles.
        if (this.hasFarTarget) {
            const dx = (v.x - this.base.x) * 0.5;
            const dy = (v.y - this.base.y) * 0.5;
            this.farTarget.setAttribute('transform', `translate(${dx.toFixed(1)} ${dy.toFixed(1)})`);
        }
    }

    animateTo(target, duration = 520) {
        cancelAnimationFrame(this.frame);
        const to = this.clamp(target);
        if (reducedMotion()) { this.view = to; this.apply(); return; }
        const from = { ...this.view };
        const start = performance.now();
        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const k = easeOutCubic(t);
            this.view = {
                x: from.x + (to.x - from.x) * k,
                y: from.y + (to.y - from.y) * k,
                w: from.w + (to.w - from.w) * k,
                h: from.h + (to.h - from.h) * k,
            };
            this.apply();
            if (t < 1) { this.frame = requestAnimationFrame(step); }
        };
        this.frame = requestAnimationFrame(step);
    }

    glideTo(star, zoom) {
        const x = parseFloat(star.dataset.x);
        const y = parseFloat(star.dataset.y);
        const w = this.base.w / Math.min(MAX_ZOOM, zoom);
        const h = w * (this.base.h / this.base.w);
        this.animateTo({ x: x - w / 2, y: y - h / 2, w, h });
    }

    zoomAround(factor, cx, cy, animate = true) {
        const w = this.view.w / factor;
        const h = this.view.h / factor;
        const target = {
            w,
            h,
            x: cx - (cx - this.view.x) * (w / this.view.w),
            y: cy - (cy - this.view.y) * (h / this.view.h),
        };
        if (animate) { this.animateTo(target, 320); } else { this.view = this.clamp(target); this.apply(); }
    }

    zoomIn() { this.zoomAround(1.4, this.view.x + this.view.w / 2, this.view.y + this.view.h / 2); }

    zoomOut() { this.zoomAround(1 / 1.4, this.view.x + this.view.w / 2, this.view.y + this.view.h / 2); }

    reset() { this.animateTo({ ...this.base }); }

    toSvg(clientX, clientY) {
        const rect = this.svgTarget.getBoundingClientRect();
        const scale = Math.max(this.view.w / rect.width, this.view.h / rect.height);
        const offsetX = (rect.width - this.view.w / scale) / 2;
        const offsetY = (rect.height - this.view.h / scale) / 2;
        return {
            x: this.view.x + (clientX - rect.left - offsetX) * scale,
            y: this.view.y + (clientY - rect.top - offsetY) * scale,
            scale,
        };
    }

    wheel(event) {
        if (!event.ctrlKey && !event.metaKey) { return; } // la molette seule fait défiler la page
        event.preventDefault();
        cancelAnimationFrame(this.frame);
        const p = this.toSvg(event.clientX, event.clientY);
        this.zoomAround(Math.exp(-event.deltaY * 0.0025), p.x, p.y, false);
    }

    pointerDown(event) {
        if (event.button !== 0) { return; }
        cancelAnimationFrame(this.frame);
        this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        this.dragged = false;
        this.moved = 0;
        this.velocity = { x: 0, y: 0 };
        this.last = { x: event.clientX, y: event.clientY, t: performance.now() };
        if (this.pointers.size === 2) { this.pinch = this.pinchDistance(); }
    }

    pointerMove(event) {
        const previous = this.pointers.get(event.pointerId);
        if (!previous) { return; }
        this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

        if (this.pointers.size === 2 && this.pinch) {
            const distance = this.pinchDistance();
            const [a, b] = [...this.pointers.values()];
            const centre = this.toSvg((a.x + b.x) / 2, (a.y + b.y) / 2);
            this.zoomAround(distance / this.pinch, centre.x, centre.y, false);
            this.pinch = distance;
            this.dragged = true;
            return;
        }

        const dx = event.clientX - previous.x;
        const dy = event.clientY - previous.y;
        this.moved += Math.abs(dx) + Math.abs(dy);
        if (this.moved < 5) { return; }
        if (!this.dragged) {
            this.dragged = true;
            this.svgTarget.setPointerCapture(event.pointerId);
            this.svgTarget.classList.add('is-dragging');
        }
        const { scale } = this.toSvg(event.clientX, event.clientY);
        this.view = this.clamp({ ...this.view, x: this.view.x - dx * scale, y: this.view.y - dy * scale });
        this.apply();

        const now = performance.now();
        const dt = Math.max(1, now - this.last.t);
        this.velocity = { x: (-dx * scale) / dt, y: (-dy * scale) / dt };
        this.last = { x: event.clientX, y: event.clientY, t: now };
    }

    pointerUp(event) {
        this.pointers.delete(event.pointerId);
        if (this.pointers.size < 2) { this.pinch = null; }
        this.svgTarget.classList.remove('is-dragging');
        if (this.dragged && this.pointers.size === 0 && !reducedMotion()) { this.coast(); }
        // Le clic qui suit un glissement ne doit pas ouvrir d'étoile : remis à zéro juste après.
        window.setTimeout(() => { this.dragged = false; }, 0);
    }

    // Inertie : la constellation continue de glisser puis ralentit doucement.
    coast() {
        let { x: vx, y: vy } = this.velocity;
        let previous = performance.now();
        const step = (now) => {
            const dt = now - previous;
            previous = now;
            vx *= Math.pow(0.92, dt / 16);
            vy *= Math.pow(0.92, dt / 16);
            this.view = this.clamp({ ...this.view, x: this.view.x + vx * dt, y: this.view.y + vy * dt });
            this.apply();
            if (Math.abs(vx) + Math.abs(vy) > 0.01) { this.frame = requestAnimationFrame(step); }
        };
        this.frame = requestAnimationFrame(step);
    }

    pinchDistance() {
        const [a, b] = [...this.pointers.values()];
        return Math.hypot(a.x - b.x, a.y - b.y) || 1;
    }
}
