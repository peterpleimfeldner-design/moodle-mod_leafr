#!/usr/bin/env bash
# Upgrade check on a local test site (see README.md). Back up the database first!
#
# Usage: bash run-local.sh <moodle dir> <plugin dir in moodle> <previous tag> <work dir>
# Example (Windows, Git Bash):
#   bash .github/scripts/upgrade-check/run-local.sh /c/moodle/server/moodle \
#       /c/moodle/server/moodle/mod/leafr v1.3.0 /tmp/upgradecheck
#
# Expects the site to have the previous release installed already (step 1 of README.md); this
# script seeds data, takes the snapshots, installs the code of the current checkout and upgrades.
set -euo pipefail

moodle="$1"
plugindir="$2"
previous="$3"
work="$4"
repo="$(cd "$(dirname "$0")/../../.." && pwd)"
here="$repo/.github/scripts/upgrade-check"
mkdir -p "$work"

installed=$(php -r "define('CLI_SCRIPT', true); require('$moodle/config.php');
    echo get_config('mod_leafr', 'version');")
expected=$(git -C "$repo" show "$previous:version.php" | sed -n 's/.*plugin->version *= *\([0-9]*\).*/\1/p')
if [ "$installed" != "$expected" ]; then
    echo "Installed mod_leafr version $installed is not $previous ($expected). Install $previous first." >&2
    exit 1
fi

php "$here/seed.php" "$moodle/config.php"
php "$here/snapshot.php" "$moodle/config.php" "$work/before.json"

# Replace the plugin code with the current checkout (exactly what a release ZIP contains).
rm -rf "$plugindir"
mkdir -p "$plugindir"
git -C "$repo" archive HEAD | tar -x -C "$plugindir"
php "$moodle/admin/cli/upgrade.php" --non-interactive
php "$moodle/admin/cli/purge_caches.php"

php "$here/snapshot.php" "$moodle/config.php" "$work/after.json"
php "$here/compare.php" "$work/before.json" "$work/after.json"
