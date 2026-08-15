# Tag Manager

Matomo Tag Manager is activated on this instance. It is the place to add **marketing and
third-party tags** (pixels, conversion tags, chat widgets) without editing site code.

Enabled 2026-08-14.

## The one rule that matters

**The Matomo pageview is NOT in the container, and must not be added to it.**

The site tracks pageviews from an inline snippet in
`wp-content/mu-plugins/aspendora-matomo-tracking.php` on the WordPress side. Two reasons it
stays there:

1. **Double counting.** Adding any tag to the container that tracks a pageview means every visit
   is recorded twice. The auto-generated container Matomo ships came with exactly such a tag —
   it was deleted before the container was ever published.
2. **Resilience.** The whole tracking setup is deliberately hardened against content blockers —
   bland first-party domain, `/js/` endpoint, no `matomo.js`/`analytics.*` in any URL. A tag
   container is one more request that can fail or be blocked. Core analytics must not depend on
   it; marketing pixels can.

If you ever do want the container to own the pageview, it means removing the inline snippet in
the same change and re-verifying — not just adding a tag.

## Containers

| Site | Container | Where it loads |
|---|---|---|
| 1 — aspendora.com | `BHo9F6Wm` | `aspendora-matomo-tracking.php` mu-plugin (v1.3+), same `wp_head` hook and the same "don't track editors" gate as the tracker |
| 2 — aspendoracompliance.com | `BnFlas5j` | **not installed yet** — container exists and is published, but no snippet is on that site |

Both are served first-party from `https://hub.aspendora.com/js/container_<id>.js`, not from
`analytics.aspendora.com`. That is required, not cosmetic — see the mu-plugin's header comment.

## Adding a tag

1. Administration → Tag Manager → pick the container → Tags → Create new tag.
2. Choose the tag type (Custom HTML, Meta Pixel, Google Ads conversion, …), pick or create a
   trigger (a "Pageview" trigger already exists in both containers).
3. **Publish** to the `live` environment. Nothing reaches the site until you publish.
4. Verify the tag actually fires — the container's preview/debug mode, or check the page source.

## Two traps

- **Published container files live in the webroot, which is an anonymous volume.** The documented
  redeploy uses `--renew-anon-volumes`, so every deploy wipes `js/container_*.js` and the live
  site's container 404s — silently, because the page still loads fine. The entrypoint
  (`deploy/docker-entrypoint-aspendora.sh`) now regenerates released containers in the background
  on every start to heal this. If you ever see a container 404, run
  `./console tagmanager:regenerate-released-containers`.
- **Custom HTML tags execute arbitrary JavaScript on the site.** Matomo's own README flags this
  as an XSS vector if an admin account is compromised. The restriction lives in Administration →
  General Settings (`restrictCustomTemplates`, default: admins only). Keep it no looser than that,
  and treat "who has admin on the analytics instance" as equivalent to "who can run JS on
  aspendora.com".

## Not migrated (deliberately)

These still load from WordPress plugins rather than the container. Each is a small project of its
own, because the plugins do more than inject a snippet:

- **Meta Pixel** — `official-facebook-pixel` plugin (also does advanced matching / server events).
- **Google tag `GT-PZ6CL2T7`** — Site Kit; moving it would break Site Kit's own reporting link.

Migrating them is the payoff for having a tag manager: it consolidates tracking and stops
requiring code changes for marketing. Do them one at a time, verifying each before the next.
