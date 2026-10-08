# Lwotowone learning progress and events update

Apply this update after the enterprise/earnings development update delivered previously. This ZIP contains complete replacement files and new files, not a new application.

1. Back up or commit your local project changes.
2. Extract this ZIP to a temporary folder.
3. Copy the included project folders and README.md / VALIDATION.md into your existing project, preserving their paths and replacing the corresponding files. Review any local edits before replacing a file.
4. Run the checks below. No database migration is required.

Project: D:\projects\lwotowone-web

```powershell
Set-Location D:\projects\lwotowone-web
composer install --no-interaction --prefer-dist
php artisan migrate --force
php artisan optimize:clear
composer lint
composer test
```

Deploy the web update before the mobile update. Existing cancelled registrations can register again if the event is published, has not started and has capacity. Cancellation preserves the original registration ID and created date. Start-time and capacity checks are enforced on the server.

Updated files:

- README.md
- VALIDATION.md
- app/Services/LearningProgress.php
- app/Services/Snapshot.php
- app/Services/Workflow.php
- resources/views/portal/course.blade.php
- resources/views/portal/dashboard.blade.php
- resources/views/portal/events.blade.php
- resources/views/portal/progress.blade.php
- resources/views/portal/section.blade.php
- tests/Feature/LearningEventsTest.php
