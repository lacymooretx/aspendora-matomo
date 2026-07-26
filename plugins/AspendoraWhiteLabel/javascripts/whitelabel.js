/* Aspendora white-label: brand the browser title. */
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
