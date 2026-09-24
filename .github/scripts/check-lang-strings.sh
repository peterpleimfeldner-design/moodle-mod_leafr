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
# Fails if a language string used in PHP, JavaScript or Mustache is missing in a component's
# lang/en file, or if its English and German language files do not define the same keys. Checks
# mod_leafr itself plus every installed leafrtool_* subplugin under tool/.
#
# @package   mod_leafr
# @copyright 2026 Peter Pleimfeldner
# @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

set -euo pipefail
cd "$(dirname "$0")/../.."

status=0

keys() {
    grep -oE '^.string\[.[^'"'"']+.\]' "$1" | cut -d"'" -f2 | sort -u
}

# Checks one component. $1 = directory the component lives in (relative to repo root),
# $2 = component name(s) matched in get_string()/{{#str}} calls, as a regex alternation.
check_component() {
    local dir="$1"
    local names="$2"
    local en de
    # The root mod_leafr component's lang file is lang/en/leafr.php, subplugins use their own
    # frankenstyle name (e.g. lang/en/leafrtool_confirm.php).
    if [ "$dir" = "." ]; then
        en="lang/en/leafr.php"
        de="lang/de/leafr.php"
    else
        local component
        component=$(basename "$dir")
        en="$dir/lang/en/leafrtool_${component}.php"
        de="$dir/lang/de/leafrtool_${component}.php"
    fi

    local excludedir=""
    if [ "$dir" = "." ]; then
        excludedir="--exclude-dir=tool"
    fi

    local used
    used=$( {
        grep -rhoE $excludedir "get_string\('[A-Za-z0-9_:]+', *'($names)'" --include='*.php' "$dir" \
            | sed -E "s/get_string\('([^']+)'.*/\1/"
        grep -rhoE $excludedir "\{\{#str\}\} *[A-Za-z0-9_:]+, *($names)" --include='*.mustache' "$dir" \
            | sed -E "s/\{\{#str\}\} *([^,]+),.*/\1/"
        if [ -f "$dir/amd/src/reader.js" ]; then
            sed -n "/STRING_KEYS = \[/,/\];/p" "$dir/amd/src/reader.js" | grep -oE "'[a-z_]+'" | tr -d "'"
        fi
        if [ -f "$dir/amd/src/confirm.js" ]; then
            grep -oE "key: *'[a-z_]+'" "$dir/amd/src/confirm.js" | grep -oE "'[a-z_]+'" | tr -d "'"
        fi
    } | sort -u )

    local defined
    defined=$(keys "$en")
    for key in $used; do
        if ! grep -qx "$key" <<< "$defined"; then
            echo "Missing string '$key' in $en"
            status=1
        fi
    done

    if ! diff <(keys "$en") <(keys "$de") > /dev/null; then
        echo "The keys of $en and $de differ:"
        diff <(keys "$en") <(keys "$de") || true
        status=1
    fi

    echo "$(basename "$dir"): $(wc -w <<< "$used") used strings checked against $en"
}

check_component "." "leafr|mod_leafr"
for tooldir in tool/*/; do
    [ -f "${tooldir}version.php" ] || continue
    check_component "${tooldir%/}" "leafrtool_$(basename "$tooldir")"
done

if [ "$status" -eq 0 ]; then
    echo "All checked strings are defined, en and de define the same keys."
fi
exit $status
