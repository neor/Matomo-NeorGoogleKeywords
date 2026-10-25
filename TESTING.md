# Additional interface languages — 2026-10-25

Added direct translations for this custom plugin in French (`fr`), Spanish (`es`), Portuguese (`pt`), German (`de`) and Italian (`it`). Each new catalog contains all 223 English source keys with compatible placeholders and unchanged technical parameter names. No evidence of Weblate management was found in the plugin files or translation history.

The standalone `php plugins/NeorGoogleKeywords/tests/i18n.php` check now covers all seven languages: catalog completeness, nonempty values, matching placeholders, six report sections, widget rendering, translated chart weekdays, escaped query attributes, localized PHP errors and English fallback for an unsupported language (`nl`). It passed on PHP 8.4.27 using Matomo's bundled translator and Twig. The changed test file also passed `php -l` and the diff passed whitespace checks. DDEV, PHPCS and PHPStan were unavailable; no live browser or Google API verification was performed for these translations.

# GitHub publication preparation — 2026-10-25

Validated on Windows with PHP 8.4.27 and Node.js 24.11.0, using the surrounding Matomo installation's bundled dependencies:

- All nine standalone checks below passed.
- All 27 PHP files passed `php -l`.
- Both JavaScript files passed `node --check`.

Run from the Matomo root (set `MATOMO_TEST_ROOT` to that absolute directory for the chart check):

```bash
php plugins/NeorGoogleKeywords/tests/unit.php
php plugins/NeorGoogleKeywords/tests/menu.php
php plugins/NeorGoogleKeywords/tests/date-filter.php
php plugins/NeorGoogleKeywords/tests/rank-highlights.php
php plugins/NeorGoogleKeywords/tests/dashboard-context.php
php plugins/NeorGoogleKeywords/tests/theme.php
php plugins/NeorGoogleKeywords/tests/chart.php
php plugins/NeorGoogleKeywords/tests/i18n.php
php plugins/NeorGoogleKeywords/tests/credentials.php
node --check plugins/NeorGoogleKeywords/javascripts/menu-nav.js
node --check plugins/NeorGoogleKeywords/javascripts/date-filter.js
```

These are standalone scripts, not a PHPUnit suite. DDEV was unavailable, so the Matomo DDEV runner was not used. No new tests were added: publication preparation changes documentation, package metadata and distribution exclusions, without changing runtime behavior.

Database and section integration tests were not run because no disposable test database was supplied. Live Google API, browser, scheduler, PHPCS and PHPStan checks were not run in this pass. Earlier validation records below describe their original environments and dates.

# Translations — 2026-10-20

Windows credential-path check: `php plugins/NeorGoogleKeywords/tests/credentials.php`. Uses a synthetic key in a temporary directory, verifies Windows separators/case and rejects keys in Matomo or the web root, traversal, relative/missing paths and invalid key format. No real credentials or network calls.

Run `php plugins/NeorGoogleKeywords/tests/i18n.php` from the Matomo root. Uses Matomo's JSON loader, translator and Twig with fixture data; no database or Google API.

Checks matching English/Russian keys and placeholders, production translation references, both languages across all five sections and the widget, translated chart weekdays, escaped query attributes, PHP errors and English fallback. Existing unit, chart, date-filter, dashboard-context, rank-highlights and theme checks also passed, along with PHP/JS syntax and whitespace checks. Live browser, Google API and database checks were not run; PHPCS/PHPStan executables are unavailable locally.

# TOP20 and rolling-week update 0.2.2 — 2026-09-29

Verified locally with PHP 8.3.6, MariaDB 10.11 and Matomo 5.14.0 Twig:
- Both TOP20 tables: three columns in both directions, numeric ordering, unchanged membership and independent sort state preserved by links.
- Header direction toggles, invalid parameter fallback, Google query URL encoding including Cyrillic and special characters, escaped HTML and new-tab link attributes.
- Weekly table includes seven calendar days ending at the latest imported date for the current resource; populated fixture returns 20 phrases and seven imported days.
- Existing unit, database, section rendering and combined-chart checks passed; all PHP files syntax checked.

No schema changes. No live Google or browser verification for this patch; the production PHP 8.4/MariaDB 11.8 environment was not exercised locally.

# Combined chart update 0.2.1 — 2026-09-28

The stream template now renders a single grouped daily histogram with two explicitly labelled independent axes. Existing Overview data, imports and database schema are unchanged.

Focused checks passed with Matomo 5.14.0 Twig / PHP 8.3.6: one SVG, paired metric bars, day links, missing-day hatching, true zero-height bars, independent scaling, positive geometry, labelled axes and empty history. Run `MATOMO_TEST_ROOT=/path/to/matomo php tests/chart.php` to reproduce. No browser or live Matomo visual verification is claimed for this patch.

# Five-section update 0.2.0 — 2026-09-28

- 23 behavioural checks: import, date boundaries, missing versus zero days, safe chart scale, per-day log and failure stage.
- 41 database checks on local MariaDB 10.11.14: previous sorting/transaction tests plus overview totals, daily data, top20, resource-bound connection status and 1,000-event retention.
- Five controller sections rendered using actual Matomo 5.14.0 Twig with a fixture layout and stubbed Matomo access/session APIs. Verified navigation, escaped log content, no private-key output, denied admin pages for viewers, nonce rejection and the seven-day web limit.
- Syntax checked on PHP 8.3.6. No schema migration; existing data/config and user-owned custom.css retained.
- Browser screenshot verification was unavailable because the headless Chromium download failed. Twig rendered all five fixture pages successfully.
- The test fixture is not a live Matomo deployment. Google credentials, production cron and the user's PHP 8.4/MariaDB 11.8 were not exercised here.

Section integration test: set the disposable DB environment from below, plus MATOMO_TEST_ROOT (Matomo source including vendor) and optionally NGK_RENDER_DIR for fixture HTML, then run `php tests/sections.php`. Do not run database tests against production.

# Sorting update 0.1.1 — 2026-09-28

Verified on PHP 8.3.6 / MariaDB 10.11.14 with Matomo 5.14.0 libraries:
- All 20 combinations of five columns, two directions and query/page views, across 105 rows and two pages.
- Sort is applied in SQL before LIMIT/OFFSET; deterministic tie-breaking avoids repeated/skipped rows.
- Invalid SQL sort identifiers and directions safely fall back to fixed defaults.
- Existing 14 SQL checks still pass; no schema migration or import changes.
- Twig renders five accessible sort links and direction markers, with HTML escaping preserved.

The updated UI has not been tested inside the user's live Matomo installation. PHP 8.4 / MariaDB 11.8 are the target environment, not the local verification environment.

# Verification — 2026-09-28

Version 0.1.0 is an initial release targeting standalone Matomo 5.14.x, PHP 8.4 and MariaDB 11.8. It is not yet verified against the user's live Google account or deployed Matomo.

Completed locally:

- PHP syntax checks for all PHP files, PHP CLI 8.3.6.
- Class loading against actual Matomo 5.14.0 parent classes, including Controller, Menu, Tasks and ConsoleCommand.
- Matomo 5.14.0 Twig: render query and page views; escape hostile query/URL text; hide import controls for viewers.
- 17 behavioural checks with a mocked Google API: invalid dates, range limit, Google timezone, pagination past 25,000 rows, 50,000-row cap, separate property/page aggregation, property URL encoding, day import flow, replacement, error propagation, lock release, concurrency rejection and fresh-date rejection.
- 14 SQL integration checks against local MariaDB 10.11.14 using a PDO shim for Matomo's Db methods: Unicode/emoji and case sensitivity; weighted position; CTR; separate property totals; idempotency; rollback on failed replacement; page drilldown; site/property isolation; bound SQL parameters; locks; empty day; report pagination; site deletion cleanup.
- Relevant Matomo 5.14.0 source interfaces were inspected, including the SitesManager.deleteSite.end event.

Not completed here:

- Live Google service-account authorization, permissions, quotas and real property import.
- Full browser test inside a configured Matomo installation.
- Execution specifically on PHP 8.4.12 / MariaDB 11.8.5. The available test runtimes were PHP 8.3.6 / MariaDB 10.11.14.
- A long-running production cron cycle or performance testing at large-site scale.

After installation: test connection, import one old day with known Search Console data, compare dates/property/search type, rerun the same day to confirm stable counts, and inspect the journal after the next cron cycle.

Reproduce behavioural checks:

```bash
php plugins/NeorGoogleKeywords/tests/unit.php
```

SQL test requires an explicitly provided **disposable database** and PDO MySQL. It creates and removes test_neor_gkeys_rows and test_neor_gkeys_days. Never point it at production.

```bash
NGK_TEST_DSN='mysql:host=127.0.0.1;dbname=ngk_test;charset=utf8mb4' \
NGK_TEST_USER='test_user' NGK_TEST_PASSWORD='test_password' \
php plugins/NeorGoogleKeywords/tests/database.php
```
