// Accès prudent au stockage local (navigation privée, stockage bloqué).
export function read(key, fallback = null) {
    try { return window.localStorage.getItem(key) ?? fallback; } catch (e) { return fallback; }
}
export function write(key, value) {
    try { if (value === null) { window.localStorage.removeItem(key); } else { window.localStorage.setItem(key, value); } } catch (e) { /* ignoré */ }
}
