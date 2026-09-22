#!/usr/bin/env bash
#
# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
#
# Fails if a language string used in PHP, JavaScript or Mustache is missing in lang/en,
# or if the English and German language files do not define the same keys.
#
# @package   mod_leafr
# @copyright 2026 Peter Pleimfeldner
# @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

set -euo pipefail
cd "$(dirname "$0")/../.."

EN="lang/en/leafr.php"
DE="lang/de/leafr.php"
status=0

keys() {
    grep -oE '^.string\[.[^'"'"']+.\]' "$1" | cut -d"'" -f2 | sort -u
}

used=$( {
    grep -rhoE "get_string\('[A-Za-z0-9_:]+', *'(mod_)?leafr'" --include='*.php' . | sed -E "s/get_string\('([^']+)'.*/\1/"
    grep -rhoE "\{\{#str\}\} *[A-Za-z0-9_:]+, *mod_leafr" --include='*.mustache' templates | sed -E "s/\{\{#str\}\} *([^,]+),.*/\1/"
    sed -n "/STRING_KEYS = \[/,/\];/p" amd/src/reader.js | grep -oE "'[a-z_]+'" | tr -d "'"
} | sort -u )

defined=$(keys "$EN")
for key in $used; do
    if ! grep -qx "$key" <<< "$defined"; then
        echo "Missing string '$key' in $EN"
        status=1
    fi
done

if ! diff <(keys "$EN") <(keys "$DE") > /dev/null; then
    echo "The keys of $EN and $DE differ:"
    diff <(keys "$EN") <(keys "$DE") || true
    status=1
fi

if [ "$status" -eq 0 ]; then
    echo "All $(wc -w <<< "$used") used strings are defined, en and de define the same keys."
fi
exit $status
