/* Aspendora white-label: last-resort brand sweep of the browser title.
 *
 * The title is branded server-side (AspendoraTheme overrides Morpheus's layout.twig) and the
 * report/category names come from the rebranded translations, so this should normally have
 * nothing to do. It stays because the reporting UI is a SPA that rewrites document.title on
 * navigation, and a widget or a plugin we haven't rebranded can still push "Matomo" into it.
 */
(function () {
    var brand = 'Aspendora Analytics';

    function retitle() {
        if (document.title.indexOf('Matomo') !== -1) {
            document.title = document.title.replace(/Matomo/g, brand);
        }
    }

    retitle();

    var t = document.querySelector('title');
    if (t && window.MutationObserver) {
        new MutationObserver(retitle).observe(t, { childList: true });
    }
})();
