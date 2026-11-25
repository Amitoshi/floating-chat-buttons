=== Floating Chat Buttons – WhatsApp, Call & Callback ===
Contributors: amitoshi
Tags: whatsapp, contact, callback, floating buttons, telegram
Requires at least: 6.4
Tested up to: 6.8.11
Requires PHP: 7.4
Stable tag: 0.2.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Native contact buttons, private callback inbox, optional Contact Insights and weekly reports. Every included feature is free.

== Description ==

M2.3 Phase 2 Quick Setup build by Amit Jyoti Shaw. Seven channels: WhatsApp, Call, SMS, Telegram, Messenger, Email and HTTPS custom URL. No account, license, hosted chat SDK or external font is required.

Easy Setup and the shared design preview support independent desktop/mobile positions and layouts, eight executable presets, individual button styles, compact edge-docked callback buttons, launcher and form customization. Save Draft preserves the published widget. Publish displays local progress, validation and confirmation.

Advanced Display supports inclusion/exclusion by content, post type, page template, taxonomy, archive, product and URL patterns, with name search and a visibility explanation. Exclusions win. Business Hours supports independent channel and callback schedules, multiple intervals, overnight hours, site/IANA timezones and hide, unavailable, callback or available-alternative behavior as applicable.

Optional sitewide telephone-link recovery observes existing and dynamically inserted tel: links without preventing native calls. Configure number/page filters, timing, prompt copy and session limits. A click or browser return cannot confirm a connected call or a sent message.

The private inbox is on for fresh installations. Existing saved preferences, stable enquiry references, submitted details and New/Contacted/Resolved statuses survive upgrades. Store only submitted contact information, optional name/message, submission time in UTC, source page origin/path, form, known contact channel and notification handling state. Search, filter, change status, export and delete from the authorized admin inbox.

Optional local Contact Insights stores daily counts by event, channel and source, without visitor profiles or customer details in click records. Counts distinguish clicks, form opens, accepted callback/enquiry requests, form errors and notification errors. A conversion rate describes recorded form acceptance, never confirmed communication.

Optional weekly reports include seven complete local days, previous-week comparison and all retained unresolved requests, including older enquiries. Summary with an authenticated admin-inbox link is the default. Full customer details require explicit administrator opt-in. Detailed sections show up to 100 rows each, with accurate totals and a link to all retained records. Generate a private preview or send a non-personal test report from Insights.

GA4 is independently optional and uses the site's existing gtag or GTM. It sends whitelisted event metadata without customer names, contact details, messages or page URLs. No Google tag is installed by this plugin. Preview never emits analytics.

== Installation ==

1. Upload the M2.2.2 ZIP through Plugins > Add New > Upload Plugin. For M1, use WordPress's replace-installed-version flow.
2. Activate and open Contact Buttons > Easy Setup.
3. Configure destinations, design, privacy and availability. Review the desktop/mobile previews.
4. Publish. Optionally configure Insights, weekly report recipients and existing-tag GA4 integration.

Activation does not force a redirect. The floating widget remains disabled until configured and published.

== Simple setup, colors and Call Follow-up ==

Easy Setup includes introductory-bubble controls and four mobile-bar colors. The bubble defaults to hiding while the contact menu is open; Always visible and Never show are available. Color and bubble edits update the preview but remain unpublished until Publish. Reset to preset colors changes only the four palette fields. Saved custom launcher icons and bar colors survive upgrade. The open launcher uses X Close; its handset/closed icon only opens contact options and never dials.

Simple mode edits the same draft as Advanced mode. Additional enabled methods remain saved and are summarized. Show Advanced Settings exposes every existing screen. The interface preference is stored per administrator and does not publish site settings.

The mobile bar supports independent desktop layout, a configurable breakpoint, top/bottom safe areas and opt-in temporary page spacing. Call and WhatsApp open directly; More contains additional configured methods. The sample preview appears only in administration. No brand or promotional link is inserted publicly.

Optional post-call follow-up defaults off and waits ten seconds. The prompt can appear at most once per browser session where storage is available. Functional one-bit session cookies share the used/accepted state across tabs; pending timing/source is held in sessionStorage. These flags contain no visitor identifier or customer details. Close and accepted callback/enquiry submissions suppress subsequent prompts. Blocked browser storage limits enforcement across pages/tabs. The prompt waits for a visible appropriate page/return and cannot determine whether a call connected.

Future Custom HTML design editing remains unimplemented and outside this milestone.

== Local documentation ==

Help & About provides the local topic guide. Contextual information buttons explain settings and link to local documentation. The proposed future online documentation address is disabled until published. No external documentation site or search service is required.

== Frequently Asked Questions ==

= Does a button click mean a connected call or sent message? =
No. Browsers cannot verify communication outcomes in another application. Enquiry acceptance means the form was handled, not that the owner replied.

= What happens to customer details? =
Notifications use the site's WordPress mail transport. The private inbox retains submitted details for 1–365 days, 30 by default. Source URL query strings/fragments are removed. Short-lived abuse guards store keyed hashes rather than raw IP addresses. Retention applies to unresolved requests too. Inbox disabled stops future local storage; existing records remain until expired or deleted.

= What consent integration is needed? =
Analytics defaults off. When enabled, local analytics defaults to requiring consent. Integrate your consent manager using FCBConsent.setAnalytics(true/false), or the WordPress Consent API statistics category. GA4 always requires a positive analytics consent signal. Existing Google Consent Mode alone is not automatically interpreted. See the integration hint in the admin screen.

= What are the privacy implications of detailed weekly emails? =
Detailed emails and enquiry notifications copy customer information into recipient mailboxes. Configure authorized recipients and secure those mailboxes. Local deletion cannot recall emailed/exported/backed-up copies. Summary reports omit customer details. Settings export excludes stored enquiries, notification/report recipients and active tracking/report opt-ins.

= Can mail delivery be guaranteed? =
No. wp_mail() acceptance only indicates acceptance by the configured transport. The plugin reports failures and acceptance accurately; verify actual inbox/spam delivery separately. Locally saved enquiries remain accessible when notification handling fails.

= When do reports and cleanup run? =
WordPress cron runs scheduled work. Low-traffic sites can run jobs late; configure a real server cron if exact timing matters. Aggregation/reporting jobs do not run on ordinary homepage requests. Reports use the site's timezone and compare complete local days; changing the report schedule/timezone reconciles the next run.

= Can I delete or export data? =
Use Enquiries for filtered CSV exports, status updates and deletion. WordPress Privacy tools handle matching email enquiries; verify phone requests manually through the inbox. Configure retention and optional delete-on-uninstall in Privacy & Mail. Uninstall preserves customer data and aggregates by default.

= What remains before production release? =
M3: broader theme/browser/hosting, MySQL/MariaDB, multisite, accessibility, Plugin Check and public-directory identity validation. Minimum-version targeted schedule checks and PHP syntax checks do not certify every feature on every supported runtime.

== Changelog ==

= 0.2.3.1 =
* Compact Quick Setup: contact details, arrangement, existing preview and explicit Publish.
* In-place focused channel, launcher, bubble and callback editors sharing the existing draft.
* Collapsed advanced appearance options, callback privacy summary and availability feedback.
* Compatible optional bubble appearance defaults; Phase1 movement/Ripple fixes and all business settings retained.

= 0.2.3 =
* Dedicated admin-only widget movement handle; normal launcher clicks, taps and keyboard activation remain intact.
* Snapshot-based device positioning, bounded movement, cancellation and docked-bar protection.
* Quick Ripple targets the active launcher only, otherwise visible direct channels; explicit Advanced effects remain preserved.
* Pencil navigation saves the latest draft revision, including edits made during an earlier save, without publishing.

= 0.2.3 =
* Stable introductory-bubble anchor and completion-driven open/close sequencing, including rapid toggles.
* All-channel ordering, placement summaries and configurable Close placement with a stable viewport anchor.
* Independent per-channel text/icon content and finite Water Ripple or Soft Pulse effects.
* Minimal Simple controls, immediate previews and scoped draft-only resets; existing settings and records preserved.

= 0.2.2.1 =
* Clean handset launcher with animated X Close state and clear accessible state labels.
* Easy Setup introductory-bubble text, visibility and behavior controls, matching live preview and published output.
* Simple mobile-bar palette and draft-only preset color reset; saved custom icons and colors preserved.
* Plain Call Follow-up navigation, settings guidance and concise descriptions throughout administration.

= 0.2.2 =
* Simple two-minute setup and per-administrator Advanced Settings preference, with all advanced functionality preserved.
* Independent full-width top/bottom mobile contact bar, responsive grouping and explicit More/contact launcher.
* Optional session-limited post-call follow-up with visible Close and accepted-request suppression.
* Local Amit Jyoti Shaw administration preview and expanded local documentation.

= 0.2.1 =
* Design presets preserve selected channels, destinations, order, schedules and callback settings.
* Transparent expanded floating stacks, optional styled panels and accessible reduced-motion-aware animations.
* Readable channel cards, grouped settings and draft-only appearance section resets.
* Local contextual help, actual website timezone labels and stable documentation topics.

= 0.2.0 =
* Clear Publish dirty state, progress, local validation and confirmation; improved readability.
* Responsive positioning, compact callback styling, advanced appearance and safe settings portability.
* Searchable display rules, independent channel/callback business hours and sitewide call recovery.
* Consent-aware local Contact Insights, optional existing-tag GA4 and weekly reports.
* Private inbox enabled on fresh installs; additive migration preserves M1 records and preferences.

= 0.1.0 =
* M1 setup wizard, native shared widget preview and seven contact channels.
* Private enquiry foundation, stable IDs/statuses, retention, exports and privacy integration.

== External services ==

No external request is required to display the widget or use its local form. External contact links open the chosen provider (WhatsApp, Telegram, Messenger or configured destination) under that provider's terms. Phone/SMS/email links open device applications. Mail uses the site's WordPress/hosting transport.

Optional GA4 forwards consented events to the site's already configured Google tag or GTM. It does not load Google scripts. Google Analytics policies: https://policies.google.com/privacy and https://marketingplatform.google.com/about/analytics/terms/us/

== Credits ==
Amit Jyoti Shaw — https://github.com/Amitoshi — amitshaw9818@gmail.com
Brand paths: Simple Icons, CC0-1.0. Other interface icons are original GPL-compatible assets.
