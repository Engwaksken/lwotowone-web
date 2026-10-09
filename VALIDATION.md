# Current checkout verification — 9 October 2026

Verified locally with PHP **8.3.33** and the installed extensions:

| Check | Result |
|---|---|
| `composer lint` | Passed PHP syntax checks across application, migrations and tests |
| `composer test` | **75 tests, 543 assertions passed** |
| `composer validate --strict --no-check-publish` | Passed |
| `node --check public/assets/app.js` | Passed |
| `git diff --check` | Passed (Git reports configured LF/CRLF conversion notices) |
| `php artisan migrate --force` | Passed; learner MEL, payment gateway, cohort, settlement, course-selection, and employment migrations applied |
| `php artisan view:cache` / `route:list` | Passed; MEL management routes registered |
| Local HTTP smoke check | Home, login, registration, and health endpoints returned HTTP 200 |

Coverage includes onboarding/payment gating, cohort-based learner number generation, conditional employment/employer validation, profile tabs and participant navigation, global public search, MEL payment destinations and settlements, staff-owned learner outcome updates, document downloads, notifications, enterprise workflows, event registration and table filtering. The CI matrix uses PHP 8.3 and 8.4.

The 9 October checks also cover appearance settings and branding uploads, public help/accessibility markup, encrypted AI settings, mocked AI chat and mentor recommendations, and inline course resource viewing. PHP lint, Composer metadata validation, JavaScript syntax and patch whitespace checks passed.

AI provider settings verification covers native Anthropic/Gemini/Cohere formats, OpenAI reasoning-model token parameters, unsaved connection testing, reuse of encrypted saved keys, provider-change key protection, sanitized errors, administrator authorization, secret exclusion from validation old input, and Dashboard/Profile/Logout topbar order. Provider HTTP calls are mocked; live provider connections and browser interaction checks were not run in this session.

Payment/font/enrollment verification covers type-specific payment validation, encrypted credentials and secret exclusion, learner-visible destination details, custom font rendering and CSS-injection rejection, CMS/enrollment status filtering, CSV template downloads, BOM/CRLF support, atomic import rollback, duplicate/malformed/inactive-cohort rejection, repeat imports without duplicate enrollment or sequence consumption, bulk-selection rollback, and manager-only bulk routes. The migration is exercised by the isolated SQLite feature suite. Live provider API processing and production MySQL deployment were not performed in this session.

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
