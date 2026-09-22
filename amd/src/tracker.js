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
 * Sends the seen pages and the reading position to the server in small batches.
 *
 * @module     mod_leafr/tracker
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/** Delay before changes are sent, in milliseconds. */
const SEND_DELAY = 1500;

export default class Tracker {

    /**
     * Constructor.
     *
     * @param {Object} options
     * @param {number} options.cmid Course module id
     * @param {number} options.totalPages Number of pages of the PDF
     * @param {Function} options.onCompleted Called once when the completion rule becomes fulfilled
     */
    constructor(options) {
        this.cmid = options.cmid;
        this.totalPages = options.totalPages;
        this.onCompleted = options.onCompleted;
        this.pending = new Set();
        this.sent = new Set();
        this.currentPage = 0;
        this.sentPage = 0;
        this.totalSent = false;
        this.timer = null;
        this.sending = false;

        this.flush = this.flush.bind(this);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                this.flush();
            }
        });
        window.addEventListener('pagehide', this.flush);
    }

    /**
     * Records the pages that are visible now.
     *
     * @param {number} currentPage Reading position
     * @param {number[]} visiblePages Pages the user sees
     */
    record(currentPage, visiblePages) {
        this.currentPage = currentPage;
        visiblePages.forEach((page) => {
            if (!this.sent.has(page)) {
                this.pending.add(page);
            }
        });
        if (this.pending.size || this.currentPage !== this.sentPage || !this.totalSent) {
            clearTimeout(this.timer);
            this.timer = setTimeout(this.flush, SEND_DELAY);
        }
    }

    /**
     * Sends the collected changes.
     */
    flush() {
        clearTimeout(this.timer);
        if (this.sending) {
            this.timer = setTimeout(this.flush, SEND_DELAY);
            return;
        }
        if (!this.pending.size && this.currentPage === this.sentPage && this.totalSent) {
            return;
        }
        const pages = [...this.pending];
        const currentPage = this.currentPage;
        this.pending.clear();
        this.sending = true;
        this.send(pages, currentPage);
    }

    /**
     * Sends pages and reading position to the server.
     *
     * @param {number[]} pages Newly seen pages
     * @param {number} currentPage Reading position
     * @returns {Promise}
     */
    send(pages, currentPage) {
        return Ajax.call([{
            methodname: 'mod_leafr_page_viewed',
            args: {
                cmid: this.cmid,
                pages: pages,
                currentpage: currentPage,
                totalpages: this.totalPages,
            },
        }])[0].then((result) => {
            pages.forEach((page) => this.sent.add(page));
            this.sentPage = currentPage;
            this.totalSent = true;
            if (result.completed && this.onCompleted) {
                this.onCompleted();
                this.onCompleted = null;
            }
            return result;
        }).catch(() => {
            // Keep the pages and try again with the next change.
            pages.forEach((page) => this.pending.add(page));
        }).then(() => {
            this.sending = false;
            return null;
        });
    }
}
