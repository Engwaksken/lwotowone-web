# Current checkout verification — 8 October 2026

Checks available in the current Windows workspace:

| Check | Result |
|---|---|
| `composer lint` | Passed PHP syntax checks across application, migrations and tests |
| `composer validate --strict --no-check-publish` | Passed |
| `node --check public/assets/app.js` | Passed |
| `git diff --check` | Passed (Git reports configured LF/CRLF conversion notices) |
| `composer test` | Not run: installed PHP is 8.2.12; the project requires PHP 8.3+ and PHPUnit 12 refuses to start |
| `php artisan view:cache` / `route:list` | Not run for the same PHP 8.3 platform requirement |

The test suite contains additional learner-access, payment, notification, enterprise, event and table-filter coverage. Run it with PHP 8.3 or later; the CI matrix uses PHP 8.3 and 8.4.

## Previous development verification — 7 October 2026

Checks run locally under PHP **8.5.11** using the locked dependencies:

| Check | Result |
|---|---|
| `composer lint` | Passed without PHP syntax errors |
| `composer test` | **40 tests, 219 assertions passed** |
| `composer validate --strict --no-check-publish` | Passed |
| `git diff --check` | Passed |
| JavaScript syntax (`node --check public/assets/app.js`) | Passed |

New notification coverage verifies FCM service-account token exchange and caching, FCM v1 requests, clearing unregistered device tokens, participant-only token registration/removal, Twilio form requests, and safe behavior when provider configuration is absent. Table-filter coverage checks practical activity search/date filtering/pagination, review queue search/status/week filtering, and CMS record search/date filtering. Provider HTTP calls are mocked; live Google/Twilio delivery is not validated. The FCM token column requires applying the new migration before deployment.

## Previous web baseline validation — 7 October 2026

Verified against this checkout using an isolated PHP **8.3.35** runtime and locked Composer dependencies:

| Check | Result |
|---|---|
| Locked dependency installation | Passed; no dependency versions changed |
| `composer validate --strict --no-check-publish` | Passed |
| `composer audit --locked` | No security vulnerability advisories found |
| PHP source lint (`composer lint`) | Passed, including the repaired notification service |
| Feature suite (`composer test`) | **27 tests, 173 assertions passed** |
| Clean SQLite migration and production-content seed | Passed against a separate temporary database with `SEED_DEMO=false` |
| Route and scheduler registration | Passed; 44 application routes and 3 scheduled commands listed |
| Patch whitespace check | Passed |

New regression tests cover bulk-messaging role authorization, validated broadcast payloads, enterprise page rendering, account isolation, enterprise update ownership/replay/history preservation, inclusive custom dates, current week/month/year boundaries, invalid filters, literal description search, pagination and summary totals unaffected by search.

The CI workflow now prepares its own environment, installs locked dependencies, lints source, checks a clean installation and runs PHPUnit on PHP 8.3 / 8.4 with generated coverage artifacts. Remote GitHub Actions execution and coverage generation have not been observed locally; the local runtime has no coverage extension.

Provider delivery, Stripe live-mode payments, MySQL concurrency, live SMTP, browser accessibility, production deployment/recovery and native mobile acceptance remain unverified. Flutter source is outside this repository. The earlier test counts below describe prior deliveries and are not the current checkout's test inventory.

## Historical initial build validation — 4 October 2026

| Check | Result |
|---|---|
| Laravel dependency installation and Composer metadata | Passed; Laravel 13.34.0, Sanctum 4.3.3 |
| PHP source syntax | 34 source files, no errors |
| SQLite migration and seeding | Passed |
| Laravel feature tests | 13 tests, 75 assertions passed |
| Public and CMS/participant page rendering | Passed in feature tests |
| Browser homepage | HTTP 200, stylesheet loaded |
| Responsive homepage | Inspected at desktop and 390px mobile width; no horizontal overflow |
| Staff browser login and dashboard | Passed |
| Queue and scheduler command checks | Passed |
| Flutter dependencies | Resolved and locked |
| Flutter static analysis | No issues found |
| Flutter automated tests | 6 tests passed |
| Native APK / IPA compilation | Not run; Android SDK and Xcode release environment were not available |
| Production MySQL and SMTP delivery | Not tested against a live organisation server |

Laravel tests cover participant-only registration, inactive account rejection, CMS permissions, instructor ownership, enrolment-gated progress, duplicate-safe completion, mentorship exclusivity, income ownership and sync replay, event capacity, certificate requirements, draft exclusion, public/CMS/participant view rendering, private resource access and cancelled registration capacity.

Flutter tests cover empty login validation, all participant module screen rendering, API validation and malformed-response handling, offline queue persistence across restart, one replay after reconnection, failed-action retention and account cache isolation.

Offline tests use mocked HTTP responses and secure storage. Native device secure storage, external resource opening, actual low-bandwidth use, release signing and hardware-specific behaviour still require device acceptance testing. The complete acceptance scenario is in the mobile README.

Web screenshot files in `docs/screenshots` show a locally seeded demo, not measured impact or live deployment data.

## Continued development validation

Laravel enterprise update and earnings filter tests: **17 tests, 95 assertions passed**. Coverage includes update ownership, duplicate-safe replay, date boundaries, enterprise filtering, and search without changing totals.

Flutter static analysis: **No issues found**. Flutter automated tests: **11 tests passed**. New tests cover earnings periods and API configuration. Native APK compilation and device acceptance testing remain pending.

## Learning progress and events validation

Laravel: **22 tests, 136 assertions passed**. Checks cover cancellation ownership and replay, capacity release, reuse of cancelled registrations, start-time and attendance restrictions, published-content progress, assessment-gated certificate readiness and empty-course handling.

Flutter: **16 tests passed**; static analysis reports **no issues found**. Added progress calculations and populated screen checks for certificate gating, cancellation confirmation, re-registration and closed events. Native APK compilation and physical-device testing remain pending.
