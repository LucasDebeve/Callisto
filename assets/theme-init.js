// Appliqué avant l'affichage pour éviter un flash de thème. Préférences gardées dans le navigateur uniquement.
(function () {
    try {
        var root = document.documentElement;
        var theme = localStorage.getItem('callisto.theme');
        if (theme === 'light' || theme === 'dark') { root.setAttribute('data-theme', theme); }
        var size = localStorage.getItem('callisto.textSize');
        if (size === 'grand' || size === 'tres-grand') { root.setAttribute('data-text-size', size); }
    } catch (e) { /* stockage indisponible : réglage de l'appareil */ }
})();
