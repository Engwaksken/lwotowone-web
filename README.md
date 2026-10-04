# Lwotowone development update

This package contains patches for your existing Laravel and Flutter projects.
They are based on these main branch commits:
- web: 149c9ead8e54292cfed7a33deb7941f16a7322b5
- mobile: 45b82261bcc4c7a4b2b6a08bb0ba0aac41a04ed8

Changes: participant enterprise editing with ownership checks; earnings filters by period and enterprise; custom dates and description search; Flutter API configuration validation; Android release signing setup; local APK build script; Laravel/Flutter CI and manual test APK workflow.

## Apply in PowerShell

Extract this ZIP to D:\projects\lwotowone-update. Commit or stash any local changes first.

```powershell
Set-Location D:\projects\lwotowone-web
git switch -c development/enterprise-updates
git apply --check D:\projects\lwotowone-update\lwotowone-web.patch
git apply D:\projects\lwotowone-update\lwotowone-web.patch
composer install
php artisan test
git add .
git commit -m "Add enterprise editing and earnings filters"
git push -u origin development/enterprise-updates

Set-Location D:\projects\lwotowone-mobile
git switch -c development/enterprise-and-builds
git apply --check D:\projects\lwotowone-update\lwotowone-mobile.patch
git apply D:\projects\lwotowone-update\lwotowone-mobile.patch
flutter pub get
flutter analyze
flutter test
git add .
git commit -m "Add enterprise filters and Android build workflow"
git push -u origin development/enterprise-and-builds
```

If a check fails, stop before applying that patch. It may already be applied or your files may differ from the base commit. Never force the patch over local work.

Create pull requests into main for review. No database migration is required by this update. Deploy the web API update before using the mobile enterprise-edit action.

## Build your APK

From D:\projects\lwotowone-mobile, run the command below after replacing the URL with your deployed Laravel API URL:

```powershell
.\scripts\build-apk.ps1 -ApiBaseUrl "https://YOUR-ACTUAL-HOST/api"
```

Output: build\app\outputs\flutter-apk\app-release.apk.
For production signing, configure android\key.properties and your upload keystore as described in the mobile README, then add -RequireReleaseKey. Keep signing passwords and keys out of Git.

After merging the workflow into main, GitHub Actions also provides a manual test APK build. Open "Flutter checks and test APK", select "Run workflow", and supply the actual HTTPS API URL ending in /api. Download lwotowone-test-apk from the completed run. This workflow uses debug signing for testing.

## Validation

Laravel: 17 tests, 95 assertions passed.
Flutter analysis: no issues found.
Flutter automated tests: 11 tests passed.
See the included source VALIDATION.md for the automated test result and remaining native/device checks.

GitHub publication was blocked by integration write permissions. No remote development branch, commit, pull request, or deployment was created by this update.
