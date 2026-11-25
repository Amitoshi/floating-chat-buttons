# Floating Chat Buttons: WhatsApp, Call & Callback

A WordPress plugin that adds floating contact buttons (WhatsApp, Call, SMS, Telegram, Messenger, Email or any link) and a "call me back" form, with a private inbox for the requests. No account, no external chat service, no tracking scripts.

## Why I built it

Small business clients kept asking for a WhatsApp button. The free plugins either load a heavy third-party script, push an upsell banner, or say "message sent" when nobody actually got anything. I wanted one that is light, honest about what a button click means, and keeps enquiries inside WordPress.

## Features

- **7 channels:** WhatsApp, Call, SMS, Telegram, Messenger, Email and a custom HTTPS link.
- **Easy setup** with a live desktop and mobile preview, 8 presets, separate desktop and mobile positions, a mobile bottom bar, and colours and button styles.
- **Draft and publish:** you can change things without touching the live widget until you click Publish.
- **Display rules:** show or hide by page, post type, template, taxonomy, archive or URL pattern. Exclusions always win.
- **Business hours:** separate schedules for the buttons and the callback form, with overnight hours supported.
- **Callback inbox:** stored in WordPress, with New, Contacted and Resolved statuses and data retention clean-up via cron.
- **Optional insights:** daily counts by channel. These are counts only, with no visitor profiles.
- **Optional weekly email report** and optional GA4 events through the site's existing gtag or GTM. No personal data is sent.
- **Import and export** settings as JSON.
- Built-in help pages inside WP admin.

## Technical notes

- PHP namespaced (`AmitShaw\FCB`), one class per module in `includes/`: settings, store, rules, schedule, insights, reports, submissions, widget, admin.
- **Custom DB tables** are created on activation. The schema version is stored in an option and upgraded only when it changes.
- **WP-Cron** handles retention clean-up and the weekly reports.
- **Front-end JS is vanilla,** with no jQuery. All assets are local, including the icons (Simple Icons, CC0).
- **Security:** nonces and capability checks on admin actions, sanitising on save and escaping on output, plus rate limiting and honeypot checks on the public callback form.

Requires WordPress 6.4+ and PHP 7.4+.

## Install

Download the repo as a ZIP, then go to **Plugins → Add New → Upload Plugin**, activate it and open **Contact Buttons → Easy Setup**. The widget stays off until you publish.

## Status

Version 0.2.x, in active development.

## License

GPL-2.0-or-later. Brand icons from Simple Icons (CC0). Brand names belong to their owners.
