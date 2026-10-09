# Lwotowone Enterprises Ltd — Laravel CMS and web platform

A new standalone application built from the supplied Lwotowone organisation brief. PHP 8.3+, Laravel 13, Blade, Sanctum, SQLite for local use and MySQL for deployment. Local CSS and JavaScript need no Node build step.

## Included

- Responsive public homepage, organisation pages, programme and course catalogues, opportunities, events, news/impact stories and enquiry form.
- CMS with create, edit, delete, publication status, validated forms, search, pagination, private resource uploads, SEO descriptions and editable homepage hero text.
- Participant registration, login, password reset, profile and consent.
- Course enrolment, ordered lessons, external video links, private resource downloads, progress and practical assignment submission with optional evidence.
- Instructor assessment, feedback, returned work and passing scores.
- Practical skills catalogue, activity logs and manager verification.
- Mentor profiles, availability, booking requests, confirmation, completion, cancellation and session notes.
- Jobs, internships, apprenticeships, enterprise opportunities, market linkages, applications and staff decisions.
- Enterprise ideas, business plans, stages and participant income/expense records in UGX.
- Events, capacity-limited registration and staff attendance recording.
- Queued email and in-app notices, mentorship reminders, impact CSV export and audit records.
- Internal completion certificates printable to PDF after all published lessons and practical assessments are passed.
- Participant mobile API with expiring Sanctum tokens, account ownership checks and idempotent offline-action replay.

## Local setup — Windows PowerShell

Extract the ZIP and open the `lwotowone-web` directory. Use PHP 8.3 or newer, Composer 2 and PHP extensions PDO, pdo_sqlite, pdo_mysql (for MySQL), mbstring, XML, ctype, curl, fileinfo, openssl and zip.

```powershell
composer install
Copy-Item .env.example .env
if (!(Test-Path database/database.sqlite)) { New-Item -ItemType File -Path database/database.sqlite }
php artisan key:generate
```

Edit `.env`: set your real `ADMIN_EMAIL` and a unique `ADMIN_PASSWORD` of at least 12 characters. Keep `SEED_DEMO=false` for real use. Then:

```powershell
php artisan migrate --seed
php artisan serve
```

Open http://127.0.0.1:8000. All accounts sign in at `/login` and use `/dashboard`. Only participants can self-register. Administrators create staff under **People and roles**. No administrator password is hard-coded or included in this ZIP.

For a local content demonstration only, set `SEED_DEMO=true` before seeding. This adds a labelled sample course, sample instructor and sample mentor. Their password uses the administrator password you configured; do not enable these sample users on production. Seeding preserves existing organisation content. Sample content is for demonstration and requires instructor review.

## First administration session

1. Create active instructor and mentor accounts.
2. Review Website pages: About, Approach, Impact, Privacy and Terms. The privacy and terms pages are initial drafts requiring your organisation's details and review.
3. Under Organisation settings, edit `hero_title`, `hero_eyebrow` and `hero_summary`.
4. Create a course under an existing programme and assign an instructor.
5. Add lessons, private resources and practical assignments. Publish items when ready.
6. Create mentorship slots, opportunities, events and announcements.
7. Register a participant and test enrolment, practical work, mentorship and applications end-to-end.

Programme descriptions reflect intended work from the brief. No invented impact figures, accreditation or guaranteed employment claims are included.

## Roles

| Role | Access |
|---|---|
| Administrator | All CMS modules, people/roles, settings, reviews and reports |
| Programme manager | Programme/content management, reviews and reports; cannot manage roles or settings |
| Instructor | Own courses, lessons, assignments and resources; practical submissions for those courses |
| Mentor | Own availability and mentorship bookings |
| Participant | Own learning, submissions, skill logs, mentorship, applications, enterprises and transactions |

Participants cannot access CMS routes. Instructors and mentors are constrained to assigned records on the server, including downloads. Draft content is excluded from public views and participant snapshots. Income is self-recorded; the platform does not collect or transfer money.

## Production — cPanel / Apache

1. Create a MySQL database and user. Upload the source outside the public document root where possible. Point the domain document root at `public/`.
2. Configure `.env` with `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, `SESSION_SECURE_COOKIE=true`, `DB_CONNECTION=mysql` and the database credentials. Retain your generated `APP_KEY` across updates.
3. Run `composer install --no-dev --optimize-autoloader`, `php artisan key:generate` on the first installation only, `php artisan migrate --seed --force` on first installation, then `php artisan optimize`.
4. Give the web process write access to `storage` and `bootstrap/cache`; never expose `.env`, `database`, `vendor` or private uploads to the web.
5. Configure SMTP (`MAIL_MAILER=smtp`, host, port, credentials and sender). The default `log` transport records messages locally and does not deliver email.
6. Run a supervised queue worker: `php artisan queue:work --tries=3 --timeout=60`.
7. Add the scheduler cron every minute: `* * * * * /path/to/php /path/to/lwotowone-web/artisan schedule:run`.
8. After code updates, run `php artisan migrate --force`, `php artisan optimize` and `php artisan queue:restart`.
9. Back up MySQL, private uploads and `.env` securely, and test restoration. Configure HTTPS, production email and hosting before inviting participants.

`storage:link` is unnecessary for lesson resources and submissions because they use authenticated private downloads. Browser print provides certificate PDF output; there is no third-party PDF dependency.

## Mobile API

Base URL: `https://your-domain/api`. JSON requests use `Accept: application/json` and `Authorization: Bearer TOKEN` after login.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/register` | Name, email, password, password_confirmation, consent=1; optional phone/district |
| POST | `/login` | Email and password; returns user and token |
| GET | `/snapshot` | Published content and current participant's records (profile completion and confirmed learning payment required) |
| POST | `/actions/{action}` | Execute participant workflow |
| POST | `/profile` | Update staff account details or complete a participant learner profile |
| POST | `/device-token` | Register or replace the participant's FCM device token (`token`) |
| DELETE | `/device-token` | Remove the participant's FCM device token |
| POST | `/notifications/{id}/read` | Mark own notice read |
| GET | `/resources/{id}/download` | Private resource download for enrolled course |
| POST | `/logout` | Revoke current token |

Actions: `update-enterprise` (owned enterprise_id plus title, sector, idea, business_plan and stage), `enrol`, `complete`, `submit`, `practice`, `book`, `cancel-booking`, `apply`, `enterprise`, `income`, `register-event`. Optional UUID `client_id` makes action replay idempotent per participant. The Flutter source shows each action's validated payload. `submit` accepts a multipart `file` on the web; mobile currently submits written evidence.

Snapshot sync is a full account-scoped refresh, not delta sync. It deliberately downloads text and records, and excludes automatic video downloading. Pending mobile actions are replayed before refreshing the snapshot. Maximum file upload is 10 MB.

## Validation and operational limits

Run `composer test` to execute feature tests. See `VALIDATION.md` for results from this build.

This delivery is source code, not a hosted deployment. SMTP, real learning content, mentor availability, staff identities, verified organisation contact details and release configuration must be supplied by Lwotowone. Stripe PaymentIntents and signed webhook recording are implemented for enterprise transactions; configure `STRIPE_SECRET`, `STRIPE_KEY` and `STRIPE_WEBHOOK_SECRET` before provider acceptance testing. Admins can manage learner payment instructions (IOTEC, banks, merchant codes and other methods) in MEL; receipt is currently confirmed by a manager rather than automatically reconciled. FCM v1 push and Twilio SMS delivery are implemented and require provider credentials. Participant FCM-token registration is available through the API; queued broadcasts and APNs are not implemented. Live classroom video, quizzes, multi-tenant operation, external accreditation and automatic opportunity scraping are not implemented. Email/in-app notices are implemented. Background sync is not scheduled; mobile users synchronise explicitly or when the app opens. Data privacy and terms drafts need organisation review before launch. Production MySQL and real Android/iOS device acceptance testing remain necessary.

Official references: https://laravel.com/docs/13.x/releases ; https://laravel.com/docs/13.x/sanctum ; https://docs.flutter.dev/app-architecture/design-patterns/offline-first

## Continued development

Participants can now edit their own enterprise name, sector, idea, business plan and stage from the Enterprise & earnings page. Updates retain the enterprise ID, existing transactions and creation timestamp. The new `update-enterprise` action is available through the same participant API and uses the existing ownership and replay checks.

The earnings page filters by enterprise, all time, current week/month/year or an inclusive custom date range. Income, expenses and net income use those filters. Description search affects transaction history only. Date validation and enterprise ownership are enforced on the server. No database migration is needed for this update.

GitHub Actions runs source lint, dependency checks, clean-install checks and the PHPUnit suite for pushes and pull requests on every branch using PHP 8.3 / 8.4 and an isolated SQLite database. Coverage is generated and retained as a workflow artifact. See `.github/workflows/tests.yml`. Local checks: `composer lint` and `composer test`.

## Learning progress and event update

Participant course cards, course pages and the dashboard now show lesson completion, practical assignments passed, the next incomplete lesson and completion-certificate readiness. Only published content in enrolled courses counts. Empty courses never claim a certificate. The existing server certificate checks continue to enforce these requirements.

Participants can cancel their own registered events before the start time. Cancellation keeps the registration record and releases capacity. Re-registration reuses the same record when space remains and registration is open. Past or attended registrations cannot be cancelled. Event history shows the saved status.

API additions: `POST /api/actions/cancel-event` with `registration_id` (optional UUID `client_id` for replay); `/api/snapshot` adds `course_progress`, `events[].registration_open`, and `event_registrations[].event_title` / `can_cancel`. Event changes require an online server response. No schema migration is required.

## Production baseline repair — 7 October 2026

The Enterprise & earnings page includes enterprise creation/editing, income/expense recording, paginated history, and server-validated enterprise/date filters. Description search is literal and filters history without changing summary totals. Current week starts on Monday; periods use the application timezone. No database migration is needed.

Bulk push/SMS API methods now require an active administrator or programme manager and accept bounded, validated payloads. FCM uses service-account OAuth with cached access tokens; Twilio uses configured account credentials and form-encoded requests. Participants can register/remove their device token, and unregistered tokens are cleared. Set `FCM_PROJECT_ID`, `FCM_SERVICE_ACCOUNT_JSON`, `ACCOUNT_SID`, `AUTH_TOKEN` and `FROM_NUMBER`; the service account JSON is a single-line environment value. Queued broadcasts and live provider delivery checks remain operational follow-ups. Stripe payment processing and webhook handling are implemented; live-mode provider acceptance and operational configuration remain required before production use.

Stripe PaymentIntent creation is restricted to a participant's own enterprise. Signed `payment_intent.succeeded` webhooks record payments idempotently; transaction reads are scoped to the signed-in participant. Configure the three `STRIPE_*` values and register `/api/stripe/webhook` in Stripe. Failed or pending intents are not counted as transactions.

The participant practical-skills area now separates activity logging, the skills catalogue and activity history. Activity history and staff review/CMS tables support literal search, date periods and pagination. Profile forms are responsive; dashboard statistics use compact responsive cards. Font Awesome 5 icons are used throughout the shared navigation and page headers.

Use PHP 8.3+ for both Composer and the application. PHPUnit forces an in-memory SQLite database and test-only queue/session/cache settings; it does not use the deployment database. The current verification result is recorded in `VALIDATION.md`; earlier results describe earlier deliveries, including mobile source outside this repository.

## Learner onboarding and MEL — 8 October 2026

New participants complete the tabbed learner profile at `/profile` after signup and select an available course. They indicate whether they are employed and provide an employer name when applicable. Refugee learners select an administrator-configured settlement; learners with disabilities select an impairment type. A manager confirms payment and assigns an active cohort. The cohort sets the enrollment category, enrollment date, and generated learner number; formats accept `{prefix}`, `{cohort}`, `{year}`, and `{sequence}`. Managers maintain learner outcomes and after-work status/pathway. Learning enrolment, course pages, certificates, API snapshots and private learning resources require a completed profile and confirmed payment. Managers use `/admin/mel` to review learner profiles, manage cohorts and settlements, manage educator/school/other-user/employment/finance/partnership/revenue records, manage active IOTEC/bank/merchant-code payment instructions, and upload/download supporting documents. Apply all migrations with `php artisan migrate --force` before deployment. Configured payment methods display instructions and destinations; online processing/reconciliation for those methods is not enabled.

Public content can be searched from the shared header. Results include published programmes, courses, current opportunities, events, stories and pages; draft content and expired opportunities are excluded.
