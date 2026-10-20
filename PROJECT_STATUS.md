# Project status: Georg-Kloster Prihod

## Current state

The repository contains the Georg-Kloster Prihod theme (current package 0.4.44), Parish Core (0.2.5), and Connect Hub (0.3.3). The theme has five styles, a standard WordPress widget area, native Customizer controls, English gettext source strings, and Russian, German, Polish, and Ukrainian translation catalogs. The author metadata is Atapin Vladimir / `atapin`; site https://georg-kloster.ru/; contact atapin@gmail.com.

## WordPress.org release assessment

The theme is **not ready for submission**. Current WordPress.org theme requirements prohibit external calls from theme settings/onboarding pages. `includes/plugins.php` makes a WordPress.org plugin-directory API request when an administrator clicks “Check WordPress.org” from the theme settings page. Remove or redesign this feature before submitting. The catalog’s Theme Check and Theme Unit Test Data review are also still outstanding; runtime theme tests cannot run in this workspace because a WordPress installation is unavailable.

The current `readme.txt` changelog still documents development releases, and the documentation does not include the repository GitHub URL. Do not reset the release version or clear the Git repository history before the compliance work and final checks. For a first stable public release, use version 1.0.0 rather than 0.1.0; retain Git history and condense only the public changelog to the initial release when actually preparing submission.

## Checks completed

Theme source/catalog/preview check passed: 264 messages in four locales. PHP lint passed across the repository. Theme package 0.4.44 built with 76 entries; required classic-theme files are present and screenshot is 1200 x 900. `git diff --check` passed. `tests/theme-release.php`, `tests/theme-smoke.php`, and `tests/theme-i18n.php` require WordPress at `/wordpress/wp-load.php` and therefore could not run here. Official Theme Check and Theme Unit Test Data have not been run. WordPress.org submission has not occurred.

## Next steps

Remove the theme-settings external API call, add the GitHub repository link and complete WordPress.org-facing release documentation, then run Theme Check and theme unit data on a clean WordPress install. Only after those checks pass should the theme be versioned as 1.0.0, its readme changelog reduced to the initial release, and a release ZIP built.

Last related commit: check `git log -1` on branch `main` (upstream `origin/main`).
