# OpenVote — Claude Code Reference

## Build & Dev Commands

```bash
npm run build        # Compile Gutenberg block (openvote-poll) via wp-scripts
npm run start        # Webpack dev watch mode for block development
composer install     # Install PHP dependencies (TCPDF for PDF export)
./make-install-zip.sh  # Create distributable WordPress plugin zip
```

Only the `blocks/openvote-poll/` block has a build step. Other blocks are plain PHP/JS.

## Architecture Overview

WordPress plugin targeting PHP 8.1+ and WordPress 6.4+.

### Layer structure
```
openvote.php                    ← plugin entry point, loads main class
includes/class-openvote.php     ← orchestrator: wires up all subsystems
includes/class-openvote-loader.php  ← deferred hook registration (actions/filters)
admin/class-openvote-admin.php  ← admin routing, enqueues admin assets
models/                         ← data access: Openvote_Poll, Openvote_Vote, Openvote_Survey
rest-api/                       ← REST controllers mounted at /openvote/v1/
blocks/openvote-poll/src/       ← React/Gutenberg block source
```

### Hook system
All hooks are registered through `Openvote_Loader`. Subsystems add entries via `add_action()`/`add_filter()` calls on the loader, which runs them all during `init`. Never register hooks directly on the global `add_action` outside of loader-aware bootstrap.

### REST API
Base namespace: `/openvote/v1/`
Controllers: polls, votes, groups, surveys.

### Batch processing
`includes/class-openvote-batch-processor.php` — long-running jobs (e.g. mass email sends) are split into chunks stored in WordPress transients. Progress is polled via AJAX from the admin UI.

### Email providers
Six providers selectable per-installation: WP native mail, SMTP, SendGrid (up to 1000 recipients/request), Brevo, Freshmail, GetResponse. Config stored in `wp_options`.

### Database
Schema managed by `Openvote_Activator` (current schema version: 4.2.0, 11 custom tables). Migrations run on plugin activation/update.

## Conventions

- **Class naming:** `Openvote_<Component>` (PSR-0 style, one class per file)
- **File naming:** `class-openvote-<component>.php` (lowercase, hyphen-separated)
- **i18n:** Polish source strings (`pl_PL` is the reference locale), English is the fallback. Text domain: `openvote`.
- Anonymous voting is enforced server-side; the client never decides anonymity.

## Known Issues & Security Notes

### Fixed
- **False send success in batch-processor.php** *(fixed)* — SendGrid and Brevo handlers now use `$result['sent'] === count($rows)` (same as Freshmail/GetResponse) so partial delivery is correctly classified as failure.
- **Race condition in email-rate-limits.php** *(fixed)* — `increment()` now wraps all reads and writes in a `START TRANSACTION … COMMIT` with `SELECT … FOR UPDATE` locks; object cache is invalidated after commit.
- **SMTP config not restored after error** *(fixed)* — `ajax_send_test_invitation_email()` now uses `try/finally` so `openvote_mail_method` is restored even if `wp_mail()` throws.
- **Non-atomic vote insertion** *(fixed)* — `Openvote_Vote::cast()` now validates all answers first, then wraps the insert loop in a `START TRANSACTION … COMMIT`; any insert failure triggers a `ROLLBACK`.

### By design / already mitigated
- **Public REST endpoints (polls, surveys)** — intentional; the Gutenberg block requires unauthenticated read access to render polls on the front end. Data returned is limited to poll questions, anonymized answers, and vote counts — no personal data is exposed.
- **State-changing operations via GET** — already protected; admin action handlers call `wp_verify_nonce()` before processing. No additional CSRF mitigation needed.
- **Nicename in API responses** — already anonymized via `anonymize_nicename()` for non-anonymous voters. The raw `user_nicename` is not returned; this is by design for non-anonymous poll transparency.
- **API keys stored as plaintext in wp_options** — standard WordPress ecosystem pattern. For higher-security installs, define keys as `wp-config.php` constants (e.g. `OPENVOTE_SENDGRID_KEY`) and update the option-reading helpers to prefer constants over stored values.
