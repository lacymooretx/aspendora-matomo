/*!
 * Aspendora site bundle: known-visitor identity capture, ad click-id capture,
 * media analytics, click/scroll heatmap beacons, and sampled session recording
 * (rrweb, MIT — inputs masked).
 * Page must set: window.__asp = { site:'1', hub:'https://hub.example.com/', key:'...',
 *                                 rec:1, sample:100 }
 * Served from the first-party hub domain; endpoint + filenames deliberately bland.
 *
 * CONSENT (2026-08-26): identity capture and session recording are FAIL-CLOSED. They
 * require window.aspConsent.granted('analytics') — a page with no consent manager gets
 * neither. Heatmap, media, error and click-id tracking stay on: first-party, cookieless,
 * no personal identifiers. Identities are stored as a SHA-256 hash, never a readable
 * email; a page served over plain HTTP has no crypto.subtle and so captures no identity
 * at all. Do not "temporarily" relax either of these — that is the exact combination
 * (readable email as analytics key + 100% session replay) that draws demand letters.
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
    // ---- Consent (fail closed). No manager on the page => no identity, no recording.
    function consentOk() {
        try {
            // 0. Global Privacy Control. A browser sending GPC is making a legally
            //    recognised opt-out request under several US state privacy laws
            //    (Texas TDPSA among them), so it outranks every other signal here —
            //    including an accept click, which GPC users have not meaningfully given.
            //    aspendoracompliance.com honours GPC in its own consent manager; this
            //    covers the properties that have no manager of their own.
            if (navigator.globalPrivacyControl === true) { return false; }
            // 1. Our own consent manager (aspendoracompliance.com).
            var c = window.aspConsent;
            if (c && typeof c.granted === 'function') { return !!c.granted('analytics'); }
            // 2. WordPress "GDPR Cookie Consent" / CookieYes (aspendora.com). It blocks
            //    third-party tags by rewriting them to text/plain, but it cannot block a
            //    first-party script like this one — so read its decision ourselves rather
            //    than sail past a banner the visitor already answered.
            //
            //    BOTH cookies are required, and the order matters. The plugin writes
            //    `cookielawinfo-checkbox-<category>` on the FIRST page load to seed each
            //    category's default state — it is present and reads 'yes' while the banner
            //    is still sitting there unanswered. It records a category default, not a
            //    decision. `viewed_cookie_policy=yes` is the only cookie that means the
            //    visitor actually chose. Testing the category flag alone reads an untouched
            //    banner as consent, which is precisely the hole this gate exists to close.
            //    Verified on aspendora.com 2026-08-26: clean load, banner displayed,
            //    nothing clicked -> checkbox-non-necessary=yes, viewed_cookie_policy absent.
            var answered = /(?:^|;\s*)viewed_cookie_policy=yes/.test(document.cookie);
            var m = document.cookie.match(/(?:^|;\s*)cookielawinfo-checkbox-non-necessary=([^;]*)/);
            if (m) { return answered && m[1] === 'yes'; }
        } catch (e) {}
        return false; // no recognised consent manager => capture nothing
    }
    function whenConsented(fn) {
        if (consentOk()) { fn(); return; }
        var c = window.aspConsent;
        if (!c || typeof c.onChange !== 'function') { return; }
        var done = false;
        c.onChange(function (state) {
            if (done || !state || !state.analytics) { return; }
            done = true;
            fn();
        });
    }

    // SHA-256 → hex. Resolves null where WebCrypto is unavailable (plain HTTP, ancient
    // browsers), and callers must treat null as "capture nothing" rather than falling
    // back to the readable value.
    function sha256Hex(input) {
        try {
            var c = window.crypto || window.msCrypto;
            if (!c || !c.subtle || !window.TextEncoder) { return Promise.resolve(null); }
            return c.subtle.digest('SHA-256', new TextEncoder().encode(input)).then(function (buf) {
                var b = new Uint8Array(buf), out = '';
                for (var i = 0; i < b.length; i++) { out += b[i].toString(16).padStart(2, '0'); }
                return out;
            }).catch(function () { return null; });
        } catch (e) { return Promise.resolve(null); }
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

    // ---- Identity: turn anonymous visitors into known visitors
    // Sources, by priority: decorated email-campaign link (?asp_c=<GHL contact id>),
    // previously stored id, or an email typed into any submitted form (GF included).
    // The stored id follows the visitor across pages/sessions on this device;
    // Matomo's User ID then stitches all their visits into one profile.
    function applyId(uid) {
        if (window._paq) { _paq.push(['setUserId', uid]); }
    }
    function storeId(uid) {
        var prev = null;
        try { prev = localStorage.getItem('asp_uid'); localStorage.setItem('asp_uid', uid); } catch (e) {}
        applyId(uid);
        // newly learned identity: send one ping so this visit carries the user id
        if (uid !== prev && window._paq) { _paq.push(['ping']); }
    }
    var EMAIL_RE = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;

    // Devices that visited before the hashing change still hold a readable email in
    // localStorage. Rewrite it in place and never send it; nothing else clears it.
    function migrateLegacyId(saved) {
        if (!EMAIL_RE.test(saved)) { return Promise.resolve(saved); }
        return sha256Hex(saved).then(function (h) {
            if (!h) {
                try { localStorage.removeItem('asp_uid'); } catch (e) {}
                return null;
            }
            var hashed = 'sha256:' + h;
            try { localStorage.setItem('asp_uid', hashed); } catch (e) {}
            return hashed;
        });
    }

    function initIdentity() {
        try {
            var c = new URLSearchParams(location.search).get('asp_c');
            if (c && /^[A-Za-z0-9]{8,40}$/.test(c)) {
                // Opaque CRM contact id from a decorated campaign link — not personal data
                // on its own, so it needs no hashing.
                storeId('ghl:' + c);
            } else {
                var saved = localStorage.getItem('asp_uid');
                if (saved) {
                    migrateLegacyId(saved).then(function (uid) {
                        if (!uid) { return; }
                        applyId(uid);
                        // the pageview fired before this async bundle ran — one ping
                        // attaches the user id to the current visit
                        if (window._paq) { _paq.push(['ping']); }
                    });
                }
            }
        } catch (e) {}
        document.addEventListener('submit', function (e) {
            var f = e.target;
            if (!f || !f.querySelectorAll) { return; }
            var inputs = f.querySelectorAll('input[type=email], input[name*=email i]');
            for (var i = 0; i < inputs.length; i++) {
                var v = (inputs[i].value || '').trim().toLowerCase();
                if (EMAIL_RE.test(v)) {
                    // Hash before anything is stored or transmitted. The readable address
                    // never leaves this closure.
                    sha256Hex(v).then(function (h) {
                        if (h) { storeId('sha256:' + h); }
                    });
                    break;
                }
            }
        }, { capture: true, passive: true });
    }

    // ---- JS error tracking (capped per page to bound error storms)
    var errCount = 0;
    function reportError(msg, src, line, col, stack) {
        if (errCount >= 10) { return; }
        errCount++;
        beacon({
            t: 'err',
            msg: String(msg || '').slice(0, 300),
            src: String(src || '').slice(0, 200),
            line: line | 0, col: col | 0,
            stack: String(stack || '').slice(0, 1500),
            ua: navigator.userAgent
        });
    }
    function initErrors() {
        window.addEventListener('error', function (e) {
            reportError(e.message, e.filename, e.lineno, e.colno, e.error && e.error.stack);
        });
        window.addEventListener('unhandledrejection', function (e) {
            var r = e.reason;
            reportError('Unhandled rejection: ' + (r && r.message || r), '', 0, 0, r && r.stack);
        });
    }

    // ---- A/B experiments
    // Page config: window.__asp.exp = [{name:'hero-cta', variants:['control','alt']}]
    // Assignment is persistent per device (localStorage), exposed as an html class
    // asp-exp-<name>-<variant> for the page's CSS/JS to act on, and tracked once
    // per page as an Experiment event so reports can split conversions by variant.
    function initExperiments() {
        var exps = cfg.exp;
        if (!exps || !exps.length) { return; }
        var store = {};
        try { store = JSON.parse(localStorage.getItem('asp_exp') || '{}') || {}; } catch (e) {}
        exps.forEach(function (ex) {
            if (!ex || !ex.name || !ex.variants || !ex.variants.length) { return; }
            var v = store[ex.name];
            if (!v || ex.variants.indexOf(v) === -1) {
                v = ex.variants[Math.floor(Math.random() * ex.variants.length)];
                store[ex.name] = v;
            }
            document.documentElement.classList.add('asp-exp-' + ex.name + '-' + v);
            ev('Experiment', ex.name, v);
        });
        try { localStorage.setItem('asp_exp', JSON.stringify(store)); } catch (e) {}
    }

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

    function init() {
        // Always on: first-party, cookieless, no personal identifiers.
        initErrors(); initExperiments(); initMedia(); initHeat();
        // Consent-bound: personal identity and screen content.
        whenConsented(function () { initIdentity(); initRec(); });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
