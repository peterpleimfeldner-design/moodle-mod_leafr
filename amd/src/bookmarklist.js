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
 * Content of the "Bookmarks" sidebar tab: one entry per bookmark with a thumbnail, the page
 * number and an editable note. Adding/removing bookmarks themselves happens via the toolbar
 * button or the B key (see mod_leafr/reader); this module only lists and edits them.
 *
 * @module     mod_leafr/bookmarklist
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {renderPage} from 'mod_leafr/pdf';

/** CSS width of a bookmark thumbnail. */
const THUMB_WIDTH = 56;

/** Delay before an edited note is saved, in milliseconds. */
const SAVE_DELAY = 800;

export default class BookmarkList {

    /**
     * Constructor.
     *
     * @param {Object} options
     * @param {HTMLElement} options.host Panel the list is rendered into
     * @param {Object} options.pdfDoc PDF.js document proxy
     * @param {Object} options.strings Language strings
     * @param {Map<number, string>} options.items Initial bookmarks, page number => note
     * @param {Function} options.onNavigate Called with a page number to show it
     * @param {Function} options.onSetNote Called with (page, note), returns a Promise
     * @param {Function} options.onRemove Called with a page number, returns a Promise
     */
    constructor(options) {
        this.host = options.host;
        this.pdfDoc = options.pdfDoc;
        this.strings = options.strings;
        this.items = new Map(options.items);
        this.onNavigate = options.onNavigate;
        this.onSetNote = options.onSetNote;
        this.onRemove = options.onRemove;
        this.saveTimers = new Map();
    }

    /**
     * Renders the initial list.
     */
    init() {
        this.render();
    }

    /**
     * Adds or updates a bookmark and re-renders.
     *
     * @param {number} page Page number
     * @param {string} note Note
     */
    set(page, note) {
        this.items.set(page, note);
        this.render();
    }

    /**
     * Removes a bookmark and re-renders.
     *
     * @param {number} page Page number
     */
    remove(page) {
        this.items.delete(page);
        this.render();
    }

    /**
     * Renders the list.
     */
    render() {
        this.host.innerHTML = '';
        if (!this.items.size) {
            const empty = document.createElement('p');
            empty.className = 'leafr-bookmark-empty';
            empty.textContent = this.strings.bookmark_empty;
            this.host.appendChild(empty);
            return;
        }

        const list = document.createElement('ul');
        list.className = 'leafr-bookmark-list';
        [...this.items.entries()].sort((a, b) => a[0] - b[0]).forEach(([page, note]) => {
            list.appendChild(this.buildItem(page, note));
        });
        this.host.appendChild(list);
    }

    /**
     * Builds one list entry.
     *
     * @param {number} page Page number
     * @param {string} note Note
     * @returns {HTMLLIElement}
     */
    buildItem(page, note) {
        const li = document.createElement('li');
        li.className = 'leafr-bookmark-item';

        const thumb = document.createElement('div');
        thumb.className = 'leafr-bookmark-thumb';
        const canvas = document.createElement('canvas');
        thumb.appendChild(canvas);
        renderPage(this.pdfDoc, page, canvas, THUMB_WIDTH).catch(() => null);

        const body = document.createElement('div');
        body.className = 'leafr-bookmark-body';

        const pageButton = document.createElement('button');
        pageButton.type = 'button';
        pageButton.className = 'leafr-bookmark-page';
        pageButton.textContent = this.strings.pagelabel.replace('{$a}', page);
        pageButton.addEventListener('click', () => this.onNavigate(page));

        const textarea = document.createElement('textarea');
        textarea.className = 'leafr-bookmark-note';
        textarea.value = note;
        textarea.placeholder = this.strings.bookmark_note_placeholder;
        textarea.setAttribute('aria-label', this.strings.bookmark_note_label + ' ' + pageButton.textContent);
        textarea.addEventListener('input', () => {
            clearTimeout(this.saveTimers.get(page));
            const value = textarea.value;
            this.items.set(page, value);
            this.saveTimers.set(page, setTimeout(() => this.onSetNote(page, value), SAVE_DELAY));
        });

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'leafr-bookmark-remove';
        remove.textContent = this.strings.bookmark_remove;
        remove.addEventListener('click', () => this.onRemove(page));

        body.append(pageButton, textarea, remove);
        li.append(thumb, body);
        return li;
    }
}
