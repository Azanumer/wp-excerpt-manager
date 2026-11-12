# WP Excerpt Manager

Take control of WordPress excerpts without touching code: different lengths per post type, your own read-more text, and excerpt support for pages.

## Features

- **Per-post-type lengths** — set a different excerpt word count for posts, pages and any custom post type under **Settings → Excerpt Manager** (default 55, WordPress's default).
- **Read-more text** — replace the default `[...]` ending with your own text, optionally linked to the post with a `wem-read-more` class you can style.
- **Excerpt box for pages** — adds excerpt support to pages so you can write manual excerpts for them (and use them in templates).
- **Per-post override** — an *Excerpt Length* meta box in the editor sidebar lets a single post use a different length; 0 means "use the post-type default".
- **Theme-friendly** — works through the standard `excerpt_length` and `excerpt_more` filters, so it respects any theme that already customizes excerpts.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Configure lengths and read-more text under **Settings → Excerpt Manager**.

## Uninstall

Deleting the plugin removes its settings and all per-post length overrides.
