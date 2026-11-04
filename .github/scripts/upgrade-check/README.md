# Upgrade check

Proves that upgrading from the previous release keeps all user data. Run it before every release.

1. Install the previous release (e.g. tag `v1.3.0`) on a test site.
2. `php seed.php <moodle>/config.php` creates the course "Leafr upgrade check" with data for every
   completion rule, reading progress, bookmarks with notes, chapters, a read confirmation, groups,
   completion states (one set by a teacher) and user preferences.
3. `php snapshot.php <moodle>/config.php before.json` stores every Leafr related row of the site.
4. Replace the plugin code with the new version and run `php admin/cli/upgrade.php --non-interactive`.
5. `php snapshot.php <moodle>/config.php after.json`
6. `php compare.php before.json after.json` fails on any lost or changed row. The changes an upgrade
   is allowed to make are listed, with reasons, in `expected-changes.php`; update it for every
   release that changes the database.

The CI job "Upgrade check" runs these steps on Moodle 4.5 and 5.3. Locally, back up the database
first; `run-local.sh` shows the steps for the local test site.
