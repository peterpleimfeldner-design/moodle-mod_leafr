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
 * Page thumbnails for the sidebar: rendered lazily as they scroll into view, marked with a
 * checkmark once the page has been read, and released again once they are far off-screen.
 *
 * @module     mod_leafr/thumbnails
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {releaseCanvas, renderPage} from 'mod_leafr/pdf';

/** CSS width of a thumbnail. */
const THUMB_WIDTH = 96;

/** Thumbnails further away than this from the current page are released from memory. */
const KEEP_RENDERED = 30;

export default class Thumbnails {

    /**
     * Constructor.
     *
     * @param {Object} options
     * @param {HTMLElement} options.host Element the thumbnails are rendered into
     * @param {Object} options.pdfDoc PDF.js document proxy
     * @param {number} options.total Number of pages
     * @param {Set<number>} options.seenPages Pages already read
     * @param {Set<number>} options.requiredPages Pages required by the completion rule
     * @param {number} options.currentPage Current reading position
     * @param {Object} options.strings Language strings
     * @param {Function} options.onNavigate Called with the chosen page number
     */
    constructor(options) {
        this.host = options.host;
        this.pdfDoc = options.pdfDoc;
        this.total = options.total;
        this.currentPage = options.currentPage;
        this.seenPages = options.seenPages || new Set();
        this.requiredPages = options.requiredPages || new Set();
        this.strings = options.strings;
        this.onNavigate = options.onNavigate;
        this.buttons = [];
        this.rendered = new Set();
        this.near = new Set();
    }

    /**
     * Builds the thumbnail buttons and starts observing them for lazy rendering.
     */
    init() {
        const list = document.createElement('div');
        list.className = 'leafr-thumb-list';
        for (let i = 1; i <= this.total; i++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'leafr-thumb';
            button.dataset.page = i;
            const isRequired = this.requiredPages.has(i);
            if (isRequired) {
                button.classList.add('is-required');
            }
            let label = this.strings.pagelabel.replace('{$a}', i);
            if (isRequired) {
                label += ', ' + this.strings.required_badge;
            }
            button.setAttribute('aria-label', label);
            const canvas = document.createElement('canvas');
            canvas.className = 'leafr-thumb-canvas';
            const check = document.createElement('span');
            check.className = 'leafr-thumb-check';
            check.setAttribute('aria-hidden', 'true');
            const number = document.createElement('span');
            number.className = 'leafr-thumb-number';
            number.textContent = i;
            button.append(canvas, check, number);
            button.addEventListener('click', () => this.onNavigate(i));
            list.appendChild(button);
            this.buttons.push(button);
        }
        if (this.requiredPages.size) {
            // Explains the small dot under required pages, which is not self-explanatory on its own.
            const legend = document.createElement('div');
            legend.className = 'leafr-thumb-legend';
            legend.setAttribute('aria-hidden', 'true');
            const required = document.createElement('span');
            required.className = 'leafr-thumb-legend-required';
            required.textContent = this.strings.thumbs_legend_required;
            const seen = document.createElement('span');
            seen.className = 'leafr-thumb-legend-seen';
            seen.textContent = this.strings.thumbs_legend_seen;
            legend.append(required, seen);
            this.host.appendChild(legend);
        }
        this.host.appendChild(list);

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const pageNum = parseInt(entry.target.dataset.page, 10);
                if (entry.isIntersecting) {
                    this.near.add(pageNum);
                    this.renderThumb(pageNum);
                } else {
                    this.near.delete(pageNum);
                }
            });
            this.releaseFar();
        }, {root: this.host, rootMargin: '100% 0px'});
        this.buttons.forEach((button) => this.observer.observe(button));

        this.setCurrentPage(this.currentPage);
        this.markSeen([...this.seenPages]);
    }

    /**
     * Renders one thumbnail if it is not already rendered.
     *
     * @param {number} pageNum 1-based page number
     */
    renderThumb(pageNum) {
        if (this.rendered.has(pageNum)) {
            return;
        }
        this.rendered.add(pageNum);
        const canvas = this.buttons[pageNum - 1].querySelector('.leafr-thumb-canvas');
        renderPage(this.pdfDoc, pageNum, canvas, THUMB_WIDTH).catch(() => {
            this.rendered.delete(pageNum);
        });
    }

    /**
     * Releases thumbnails far away from the current page that are not near the viewport.
     */
    releaseFar() {
        this.rendered.forEach((pageNum) => {
            if (Math.abs(pageNum - this.currentPage) > KEEP_RENDERED && !this.near.has(pageNum)) {
                releaseCanvas(this.buttons[pageNum - 1].querySelector('.leafr-thumb-canvas'));
                this.rendered.delete(pageNum);
            }
        });
    }

    /**
     * Highlights the current page (both pages of a two-page spread) and scrolls it into view.
     *
     * @param {number} pageNum 1-based page number of the first visible page
     * @param {number[]} [visible] All visible pages, defaults to just pageNum
     */
    setCurrentPage(pageNum, visible) {
        this.currentPage = pageNum;
        const current = new Set(visible && visible.length ? visible : [pageNum]);
        this.buttons.forEach((button, index) => {
            const active = current.has(index + 1);
            button.classList.toggle('is-current', active);
            if (active) {
                button.setAttribute('aria-current', 'true');
            } else {
                button.removeAttribute('aria-current');
            }
        });
        const target = this.buttons[pageNum - 1];
        if (target) {
            target.scrollIntoView({block: 'nearest'});
        }
        this.releaseFar();
    }

    /**
     * Marks pages as read.
     *
     * @param {number[]} pages Page numbers
     */
    markSeen(pages) {
        pages.forEach((pageNum) => {
            const button = this.buttons[pageNum - 1];
            if (button) {
                button.classList.add('is-seen');
            }
        });
    }

    /**
     * Stops observing and releases all rendered thumbnails.
     */
    destroy() {
        if (this.observer) {
            this.observer.disconnect();
        }
        this.buttons.forEach((button) => releaseCanvas(button.querySelector('.leafr-thumb-canvas')));
        this.buttons = [];
    }
}
