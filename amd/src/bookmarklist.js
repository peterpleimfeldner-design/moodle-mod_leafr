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

/** The note field grows with its content up to this height, then scrolls. */
const MAX_NOTE_HEIGHT = 160;

/** How long the "saved" confirmation stays visible, in milliseconds. */
const SAVED_VISIBLE = 2500;

/** Show the character counter as "near the limit" from this fraction of the maximum onwards. */
const NEAR_LIMIT_RATIO = 0.9;

export default class BookmarkList {

    /**
     * Constructor.
     *
     * @param {Object} options
     * @param {HTMLElement} options.host Panel the list is rendered into
     * @param {Object} options.pdfDoc PDF.js document proxy
     * @param {Object} options.strings Language strings
     * @param {Map<number, string>} options.items Initial bookmarks, page number => note
     * @param {number} options.maxNoteLength Maximum note length the server accepts
     * @param {string} options.printUrl URL of the printable bookmark list
     * @param {Function} options.onNavigate Called with a page number to show it
     * @param {Function} options.onSetNote Called with (page, note), returns a Promise
     * @param {Function} options.onRemove Called with a page number, returns a Promise
     */
    constructor(options) {
        this.host = options.host;
        this.pdfDoc = options.pdfDoc;
        this.strings = options.strings;
        this.items = new Map(options.items);
        this.maxNoteLength = options.maxNoteLength || 500;
        this.printUrl = options.printUrl;
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

        if (this.printUrl && this.items.size) {
            const print = document.createElement('a');
            print.className = 'btn btn-sm btn-outline-secondary leafr-bookmark-print';
            print.href = this.printUrl;
            print.target = '_blank';
            print.rel = 'noopener';
            const ns = 'http://www.w3.org/2000/svg';
            const icon = document.createElementNS(ns, 'svg');
            icon.setAttribute('aria-hidden', 'true');
            icon.setAttribute('focusable', 'false');
            icon.setAttribute('width', '16');
            icon.setAttribute('height', '16');
            icon.setAttribute('viewBox', '0 0 256 256');
            icon.setAttribute('fill', 'none');
            icon.setAttribute('stroke', 'currentColor');
            icon.setAttribute('stroke-width', '16');
            icon.setAttribute('stroke-linejoin', 'round');
            const iconPath = document.createElementNS(ns, 'path');
            // A simple printer: paper tray on top, body, printed sheet at the bottom.
            iconPath.setAttribute('d', 'M72 80V40h112v40M72 184H40V88h176v96h-32M72 152h112v64H72Z');
            icon.appendChild(iconPath);
            const text = document.createElement('span');
            text.textContent = this.strings.bookmark_print;
            print.append(icon, text);
            this.host.appendChild(print);
        }

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
        pageButton.textContent = this.strings.pagelabel.replaceAll('{$a}', page);
        pageButton.addEventListener('click', () => this.onNavigate(page));

        const label = document.createElement('label');
        label.className = 'sr-only';
        label.setAttribute('for', 'leafr-bookmark-note-' + page + '-' + Math.random().toString(36).slice(2));
        label.textContent = this.strings.bookmark_note_label + ' ' + pageButton.textContent;

        const textarea = document.createElement('textarea');
        textarea.id = label.htmlFor;
        textarea.className = 'leafr-bookmark-note';
        textarea.value = note;
        textarea.maxLength = this.maxNoteLength;
        textarea.placeholder = this.strings.bookmark_note_placeholder;

        const counter = document.createElement('span');
        counter.className = 'leafr-bookmark-counter';
        counter.setAttribute('aria-hidden', 'true');

        // Notes are saved automatically; this confirms it, so nobody looks for a "save" button.
        const saved = document.createElement('span');
        saved.className = 'leafr-bookmark-saved';
        saved.setAttribute('role', 'status');
        let savedTimer = null;
        const showSaved = () => {
            saved.textContent = this.strings.bookmark_note_saved;
            clearTimeout(savedTimer);
            savedTimer = setTimeout(() => {
                saved.textContent = '';
            }, SAVED_VISIBLE);
        };

        const meta = document.createElement('div');
        meta.className = 'leafr-bookmark-meta';
        meta.append(saved, counter);

        const updateCounter = () => {
            const used = textarea.value.length;
            counter.textContent = this.strings.bookmark_note_chars
                .replaceAll('{$a->used}', used)
                .replaceAll('{$a->max}', this.maxNoteLength);
            counter.classList.toggle('is-near-limit', used >= this.maxNoteLength * NEAR_LIMIT_RATIO);
        };
        const growNote = () => {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, MAX_NOTE_HEIGHT) + 'px';
        };
        updateCounter();
        growNote();
        textarea.addEventListener('input', () => {
            clearTimeout(this.saveTimers.get(page));
            const value = textarea.value;
            this.items.set(page, value);
            saved.textContent = '';
            this.saveTimers.set(page, setTimeout(() => {
                Promise.resolve(this.onSetNote(page, value)).then((ok) => {
                    if (ok && textarea.value === value) {
                        showSaved();
                    }
                    return ok;
                }).catch(() => null);
            }, SAVE_DELAY));
            updateCounter();
            growNote();
        });

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'leafr-bookmark-remove';
        remove.textContent = this.strings.bookmark_remove;
        remove.addEventListener('click', () => this.onRemove(page));

        body.append(pageButton, label, textarea, meta, remove);
        li.append(thumb, body);
        return li;
    }
}
