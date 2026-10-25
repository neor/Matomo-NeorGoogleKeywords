# NeorGoogleKeywords Changelog

New entries are added at the top. The current version is specified in `plugin.json`; validation results and limitations are documented in [TESTING.md](TESTING.md).

## 1.2.0 — 2026-10-25

### Added

- Per-website service account keys through `credentials_file_ID`, with the original `credentials_file` retained as a shared fallback.
- Website-specific credentials for connection checks, settings, manual imports, CLI imports and scheduled imports.
- Regression coverage for credential selection, invalid overrides and account isolation when reusing a Google client across websites.

### Fixed

- Corrected the README import command to `ngk:import`.

## 1.1.1 — 2026-10-23

### Added

- French (`fr`), Spanish (`es`), Portuguese (`pt`), German (`de`) and Italian (`it`) translations for all plugin interface strings, including reports, imports, settings, errors and chart weekdays.
- Catalog completeness, placeholder compatibility and template rendering checks for all seven supported languages.

### Changed

- Replaced dual top navigation with a unified single-line compact navigation bar styled after Matomo's native controls.
- Positioned the website selector dropdown on the left (`[angelio.ee ∨]`), styled to match Matomo's rounded top control buttons with chevron indicator and instant form auto-submission on selection.
- Placed plugin navigation links (`Overview`, `Queries`, `Pages`, `Import`, `Log`, `Settings`) directly next to the website selector in a single horizontal row.
- Retained site configuration highlighting (`color: var(--ngk-clicks, #1967d2)`) in the website selector dropdown for properties configured in Google Search Console.
- Hidden Matomo's redundant `.top_controls` container when viewing NeorGoogleKeywords reports, while seamlessly restoring it when navigating to core Matomo reports.

### Fixed

- Fixed empty data display on initial entry from the left menu: the menu link now navigates directly to the full plugin report view, defaults site resolution to the first configured Google Search Console property if none is specified or available, and avoids premature widget disabling when metadata is queried without an explicit idSite.

## 1.1.0 — 2026-10-23

### Changed

- Moved the plugin entry link from Matomo's top header navigation into the reporting sidebar menu (`GoogleSearchCategory` with icon `icon-search`).
- Integrated the plugin report directly into Matomo's analytical workspace on the right (`OverviewReport`), keeping the reporting sidebar menu visible on the left just like core analytics features.
- Enabled standard Matomo left navigation (`showMenu = true`) and added `templates/report.twig` for widget rendering without full page reload.
- Highlighted configured sites in the website selector dropdown (`select[name="idSite"]`) using `color: var(--ngk-clicks, #1967d2)` for configured `<option>` items and for the active `<select>` selection.
- Renamed Settings section card headings to "Google Cloud settings" and "NeorGoogleKeywords settings".
- Renamed the "Details" menu section to "Queries" ("Запросы").

### Added

- Added "Pages" ("Страницы") section to the plugin top navigation, rendering a dedicated report with all pages, total clicks across all keywords leading to each page, and interactive keyword badges (sorted by clicks descending, then alphabetically, with keyword click counts, tooltips, drill-down links to query trend/stats, and expandable details for pages with > 5 keywords).
- Added trend chart to the Queries ("Запросы") section, aggregating all search keywords per day (or drilled query) over the selected date range and computing daily average clicks and impressions with dashed horizontal average lines, interactive points, and daily details table.
- Highlighted the search query in both the drill-down trend heading and the pages block heading ("Pages for query:") with color `#26a69a` (`.ngk-highlight-keyword`).
- Displayed Cyrillic URLs in readable Cyrillic text in the drilled-down pages table (`rawurldecode`) with external link icon, while preserving valid URL encoding in `href`.

### Fixed

- Preserved `category` and `subcategory` parameters across all internal plugin navigation links, pagination, date ranges, and filter forms, ensuring the left sidebar menu stays on "Google Search" instead of resetting to Dashboard.
- Added client-side active category synchronization in `javascripts/menu-nav.js` to keep the "Google Search" menu item highlighted during in-page navigation and form submissions.
- Fixed error (HTTP 500: "The requested website idSite is not found in the request") when navigating from an active NeorGoogleKeywords page to core Matomo reports (such as Visitors -> Overview) via the left sidebar menu: `javascripts/menu-nav.js` now properly passes `idSite`, `period`, and `date` in the HTTP query string before the client hash fragment.

## 1.0.0 — 2026-10-20

### Added

- Multilingual support using Matomo's translation system. English and Russian translations are provided in `lang/en.json` and `lang/ru.json`; other languages fall back to English.
- Translations for report sections, the search phrases widget, chart tooltips, two-letter weekday abbreviations, the date picker and error messages. The interface follows the language selected in Matomo. Previously saved log messages retain their original language.

## 0.4 — changes from 2026-10-06

### Added

- Compact Week, Month, Quarter and All time buttons to the right of the date fields in Details, wrapping on narrow screens. Ranges end on the latest imported day; the website, query and sort order are retained, while pagination is reset.
- A dark theme following Matomo's settings, including automatic mode. Navigation, tables, cards, forms, notices, the log and the keywords widget support the theme.
- A line chart replacing the Two-month trend histogram: blue and purple lines for clicks and impressions, independent axes, tooltips and links to daily reports. Missing imports appear as gaps in the lines.

### Fixed

- The `Variable "siteName" does not exist` error when opening the plugin without `idSite`: the selected website and date context are passed to Matomo's base controller.
- The quarterly Top 20 now covers the last three calendar months through the latest imported day, with correct boundaries across months and years. Rankings, import coverage and links use the same period.

## 0.3

The release date is not recorded in the available documentation.

- Added a search phrases widget to Matomo's Referrers → Search Engines and Keywords report, with a link to the full report.
- Changed the latest available import date to today minus two days in Google's `America/Los_Angeles` timezone.
- Date fields in Details and Import use DD-MM-YYYY format and a date picker; links containing ISO dates remain supported.
- Added highlighting for Top 1 and Top 3 positions in the query table.

## 0.2.2 — 2026-09-29

- Added sorting by phrase, clicks and impressions to both Top 20 tables. Changing the order retains the same twenty leaders.
- The weekly Top 20 covers the last seven calendar days through the property's latest imported day.
- Added links to search for each phrase on Google.

## 0.2.1 — 2026-09-28

- Combined clicks and impressions into one histogram with independent axes.
- Added tooltips showing both daily values and markers for days without imports.

## 0.2.0 — 2026-09-28

- Split the interface into Overview, Details, Import, Log and Settings.
- Added summary metrics, daily charts and Top 20 tables.
- Expanded the import log with daily metrics, duration, run ID and failure stage; up to 1,000 events are retained per website.

## 0.1.1 — 2026-09-28

- Added sorting across five columns in the detailed report, including query and page views.
- Sorting is applied before the row limit; ordering remains stable across pages.

## 0.1.0 — 2026-09-28

- Initial release: Google Search Console imports using a service account, query and page reports, clicks, impressions, CTR and average position.
- Support for multiple websites, manual and CLI imports, a daily task and a log.
- Access checks and Matomo token protection for administrative requests.

Earlier history was reconstructed from `TESTING.md`, `AGENTS.md` and the source code.
