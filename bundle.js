/*!
 * Aspendora site bundle: ad click-id capture, media analytics, click/scroll heatmap
 * beacons, and sampled session recording (rrweb, MIT — inputs masked).
 * Page must set: window.__asp = { site:'1', hub:'https://hub.example.com/', key:'...',
 *                                 rec:1, sample:100 }
 * Served from the first-party hub domain; endpoint + filenames deliberately bland.
 */
(function () {
    var cfg = window.__asp || {};
    if (!cfg.hub || !cfg.site) { return; }
    var PAGE = location.href.split('#')[0];

    function beacon(payload) {
        payload.k = cfg.key; payload.idsite = cfg.site; payload.url = PAGE;
        var body = JSON.stringify(payload);
        if (navigator.sendBeacon && body.length < 60000) {
            navigator.sendBeacon(cfg.hub + 'hub.php', body);
        } else {
            fetch(cfg.hub + 'hub.php', { method: 'POST', body: body, keepalive: true }).catch(function () {});
        }
    }
    function ev(cat, action, name, value) {
        if (!window._paq) { return; }
        var args = ['trackEvent', cat, action, name];
        if (typeof value === 'number') { args.push(Math.round(value * 10) / 10); }
        _paq.push(args);
    }

    // ---- Ad click IDs (offline conversion export needs these)
    try {
        var qs = new URLSearchParams(location.search);
        ['gclid', 'msclkid'].forEach(function (p) {
            var v = qs.get(p);
            if (v && v.length > 8 && v.length < 200) { ev('AdClick', p, v); }
        });
    } catch (e) {}

    // ---- Media analytics (HTML5 video/audio)
    function mediaName(el) {
        return (el.getAttribute('title') || el.currentSrc || el.src || 'unknown').split('?')[0].slice(-120);
    }
    function watchMedia(el) {
        var name = mediaName(el), started = false, marks = {}, acc = 0, last = 0;
        el.addEventListener('play', function () {
            last = el.currentTime;
            if (!started) { started = true; ev('Media', 'play', name); }
        });
        el.addEventListener('timeupdate', function () {
            if (el.paused || !el.duration) { return; }
            var pct = 100 * el.currentTime / el.duration;
            [25, 50, 75].forEach(function (m) {
                if (pct >= m && !marks[m]) { marks[m] = 1; ev('Media', 'p' + m, name); }
            });
            if (el.currentTime > last) { acc += el.currentTime - last; }
            last = el.currentTime;
        });
        function reportTime() {
            if (acc >= 1) { ev('Media', 'time', name, acc); acc = 0; }
        }
        el.addEventListener('pause', reportTime);
        el.addEventListener('ended', function () { ev('Media', 'finish', name); reportTime(); });
        window.addEventListener('pagehide', reportTime);
    }
    function initMedia() {
        document.querySelectorAll('video, audio').forEach(watchMedia);
    }

    // ---- Heatmap: clicks + max scroll depth
    var maxScroll = 0;
    function docHeight() {
        var d = document.documentElement;
        return Math.max(d.scrollHeight, d.offsetHeight);
    }
    function initHeat() {
        document.addEventListener('click', function (e) {
            if (typeof e.pageX !== 'number') { return; }
            var w = document.documentElement.clientWidth || 1;
            beacon({ t: 'click', x: 100 * e.pageX / w, y: Math.round(e.pageY), dh: docHeight(), vw: w });
        }, { capture: true, passive: true });
        function onScroll() {
            var d = document.documentElement;
            var seen = 100 * (window.scrollY + d.clientHeight) / (docHeight() || 1);
            if (seen > maxScroll) { maxScroll = Math.min(100, Math.round(seen)); }
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        window.addEventListener('pagehide', function () {
            beacon({ t: 'scroll', sp: maxScroll, dh: docHeight(), vw: document.documentElement.clientWidth });
        });
    }

    // ---- Session recording (sampled; inputs always masked)
    function initRec() {
        if (!cfg.rec) { return; }
        var sample = typeof cfg.sample === 'number' ? cfg.sample : 100;
        if (Math.random() * 100 >= sample) { return; }
        var s = document.createElement('script');
        s.src = cfg.hub + 'vendor.js';
        s.async = true;
        s.onload = function () {
            if (!window.rrweb) { return; }
            var key = '';
            for (var i = 0; i < 4; i++) { key += Math.floor(Math.random() * 0xffff).toString(16).padStart(4, '0'); }
            var buf = [], seq = 0, start = Date.now();
            function flush(final) {
                if (!buf.length) { return; }
                var events = buf; buf = [];
                var payload = JSON.stringify({
                    k: cfg.key, idsite: cfg.site, t: 'rec', key: key, seq: seq++,
                    url: PAGE, dur: Date.now() - start, ua: navigator.userAgent, events: events
                });
                if (final && navigator.sendBeacon && payload.length < 60000) {
                    navigator.sendBeacon(cfg.hub + 'hub.php', payload);
                } else {
                    fetch(cfg.hub + 'hub.php', { method: 'POST', body: payload, keepalive: final === true }).catch(function () {});
                }
            }
            window.rrweb.record({
                emit: function (e) {
                    buf.push(e);
                    if (buf.length >= 400) { flush(false); }
                },
                maskAllInputs: true,
                blockClass: 'asp-norecord',
                sampling: { mousemove: 120, scroll: 200 }
            });
            setInterval(function () { flush(false); }, 6000);
            window.addEventListener('pagehide', function () { flush(true); });
        };
        document.head.appendChild(s);
    }

    function init() { initMedia(); initHeat(); initRec(); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
