// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The read confirmation card: reveals itself once the core reader reports that the reading
 * requirement is met, and lets the user tick a checkbox and confirm.
 *
 * @module     leafrtool_confirm/confirm
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import * as Str from 'core/str';

/**
 * Initialises the confirmation card of one activity.
 *
 * @param {Object} options
 * @param {number} options.cmid Course module id
 * @param {boolean} options.confirmed Whether the current user already confirmed
 */
export const init = (options) => {
    // $cm->id is passed through a webservice-style external_value(PARAM_INT) on confirm, but here
    // it comes straight from a PHP object cast to JSON, which can leave it as a numeric string;
    // normalise it so the strict comparison against the reader's own (real) cmid number works.
    const cmid = parseInt(options.cmid, 10);
    const root = document.querySelector('[data-region="leafrtool-confirm"][data-cmid="' + cmid + '"]');
    if (!root) {
        return;
    }

    if (options.confirmed) {
        root.hidden = false;
        return;
    }

    const checkbox = root.querySelector('[data-region="checkbox"]');
    const button = root.querySelector('[data-action="confirm"]');

    checkbox.addEventListener('change', () => {
        button.disabled = !checkbox.checked;
    });

    button.addEventListener('click', async() => {
        button.disabled = true;
        try {
            await Ajax.call([{
                methodname: 'leafrtool_confirm_set_confirmed',
                args: {cmid},
            }])[0];
            const [doneText] = await Str.getStrings([{key: 'confirm_done', component: 'leafrtool_confirm'}]);
            root.querySelector('[data-region="form"]').hidden = true;
            const done = root.querySelector('[data-region="done"]');
            done.querySelector('[data-region="donetext"]').textContent = doneText;
            done.hidden = false;
        } catch (error) {
            button.disabled = false;
        }
    });

    document.addEventListener('leafr:reading-complete', (event) => {
        if (event.detail && event.detail.cmid === cmid) {
            root.hidden = false;
        }
    });
};
