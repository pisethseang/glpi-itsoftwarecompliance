> Historical note for v0.1.1. The current v0.2.0 stylesheet uses `?v=020`.

# GLPI 12 CSS loading fix — v0.1.1

GLPI 11 and 12 expose plugin static files from the plugin `public/` directory.
In v0.1.0, the stylesheet was `css/compliance.css`, but the browser requested
`/plugins/itsoftwarecompliance/css/compliance.css`. Consequently it could not load.

The actual file is now `public/css/compliance.css`. The URL remains
`/plugins/itsoftwarecompliance/css/compliance.css` (without `/public/`).
The query string is updated to `?v=011` so browsers do not reuse cached CSS.

No changes to SQL, rules, inventory logic, data tables, or permissions.

## Verify

`curl -I 'https://YOUR-GLPI/plugins/itsoftwarecompliance/css/compliance.css?v=011'`

Expect HTTP 200 and a CSS content type. A 404 indicates the plugin assets
are not placed correctly or routing/web server configuration needs review.
Restart GLPI if opcode caching is in use, then hard refresh the browser.
