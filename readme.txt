=== Open Vote ===
Contributors: t7jk
Tags: voting, polls, surveys, e-voting, anonymous voting, groups, email invitations, elections
Requires at least: 6.4
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organisation polls and surveys: create votes with questions, manage groups, send invitations, view results (with optional anonymity).

== Description ==

Open Vote is a WordPress plugin for running electronic polls and surveys within your organisation. It lets you create polls with multiple questions and answers, manage participant groups (including by city or custom groups), send email invitations in batches, and view results with optional anonymity.

= Features =

* **Polls** — Create drafts, open and close polls. Each poll has a title, description, start/end dates, and multiple questions with up to 12 answers each (including "Abstain").
* **Anonymous or public voting** — Choose whether voters appear in results by name or as "Anonymous".
* **Groups** — Define user groups (e.g. by city from profile field). Target polls to specific groups. Sync members in batches (100 at a time) for large sites.
* **Coordinators** — Assign coordinators to groups; they can create and run polls for their groups.
* **Surveys** — Separate surveys with custom fields (short/long text, number, URL, email). Responses can be marked as draft or ready; spam flag and public submissions view (sensitive data hidden).
* **Email** — Invitations and notifications via WordPress mail, external SMTP, or SendGrid API. Batch sending with progress bar for large recipient lists.
* **Results** — Turnout, per-question counts and percentages, lists of voters and non-voters (paginated). Optional PDF export (requires Composer dependency).
* **i18n** — Polish and English; interface language follows WordPress locale.

= Requirements =

* WordPress 6.4 or later
* PHP 8.1 or later
* For PDF export: run `composer install` in the plugin directory (TCPDF)

= Good for =

* Associations, clubs, and organisations that need internal votes
* Sites with many users (batch processing avoids timeouts)
* When you need anonymity or auditable, transparent results

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via WordPress admin (Plugins → Add New → Upload).
2. Activate the plugin via the "Plugins" screen.
3. Go to **E-głosowania → Konfiguracja** (or **E-Voting → Settings** in English) to set:
   * Sender email and mail method (WordPress / SMTP / SendGrid)
   * Vote page URL (e.g. `?glosuj`)
   * User profile field mapping (first name, last name, city, etc.) if you use custom profile plugins
4. Create groups under **E-głosowania → Grupy** and optionally run "Synchronizuj wszystkie grupy-miasta" to fill groups by city.
5. Add coordinators under **E-głosowania → Koordynatorzy** if you use role-based access.
6. Create a WordPress Page or use the configured vote slug so users can open the voting page.

= Optional: PDF reports =

To enable "Pobierz wyniki (PDF)" on poll results, run in the plugin directory:

`composer install`

This installs TCPDF (LGPL). Without it, the button is hidden and a short notice is shown.

== Frequently Asked Questions ==

= What is the difference between a Poll and a Survey? =

Polls are for formal votes: one vote per user per poll, optional anonymity, results with turnout and percentages. Surveys are for collecting open-ended or structured responses (text, numbers, etc.) with draft/ready status and optional spam marking.

= Can I use this with 10,000+ users? =

Yes. Group sync, invitation sending, and result lists are processed in batches (e.g. 100 at a time) with a progress bar. List screens (e.g. user picker) are limited (e.g. 300) with an option to enter a user ID directly.

= Which languages are supported? =

Polish (when WordPress is set to Polish) and English (for any other locale). All interface strings are translatable (domain: evoting).

= Is voting really anonymous when I choose "anonymous"? =

Yes. The server forces anonymous mode: even if the client sent a different preference, the vote is stored and displayed as anonymous. No list of voters is shown for anonymous polls.

== Screenshots ==

1. Poll list (draft, open, closed) with actions: Edit, Results, Invitations
2. Poll form: questions and answers, target groups, dates
3. Groups: list and members; sync by city
4. Coordinators: assign users to groups
5. Settings: email method, SMTP/SendGrid, vote page URL, field mapping
6. Public vote page: active and ended polls, vote form
7. Results: turnout, per-question stats, voter and non-voter lists (paginated)

== Changelog ==

= 1.0.0 =
* Initial release.
* Polls: create, edit, draft/open/closed, questions and answers, target groups.
* Voting: public page, eligibility checks, anonymous or public mode.
* Groups: create, sync by city (batch), manual members.
* Coordinators: assign users to groups, limit roles.
* Surveys: create, fields (text, number, URL, email), responses, spam flag, public submissions block (sensitive data hidden).
* Email: WordPress / SMTP / SendGrid, batch invitations with progress.
* Results: turnout, lists (paginated), optional PDF (with Composer).
* i18n: Polish and English.
* Batch processing for large user bases (sync, invitations, result lists).

== Upgrade Notice ==

= 1.0.0 =
First release. Configure vote page and field mapping after activation.
